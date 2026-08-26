<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_103100_rechain_audit_hashes_per_organization.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Models\Concerns\HashChainable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Log, Schema};

/**
 * Kettet den Bestand der revisionssicheren Audit-Tabellen je Organisation um
 * (Vollscan 2026-08-23, A5, MVP-722).
 *
 * Vorher führte jede Tabelle EINE Kette; ihr Kopf in `audit_chain_heads` wurde
 * bei jedem Insert per `lockForUpdate` gesperrt und die Sperre bis zum
 * äußersten Commit gehalten — alle Mandanten schrieben also serialisiert.
 * `audit:measure-chain-contention` belegt es: 3 Organisationen × 300 Zeilen,
 * 2,40 ms/Zeile allein gegen 6,04 ms/Zeile parallel, dabei 445 von 900
 * Einfügungen als Verklemmung abgebrochen.
 *
 * Ab jetzt heißt die Kette `tabelle:organisation`
 * ({@see App\Models\Concerns\HashChained::chainName()}). Der Bestand muss
 * derselben Aufteilung folgen, sonst bricht `audit:verify` an der ersten
 * Zeile: prev_hash zeigte auf die Vorgängerzeile der TABELLE, nicht der
 * Organisation.
 *
 * Ablauf je Tabelle:
 *   1. Beweisdurchlauf über alle Zeilen in id-Reihenfolge — jede Zeile muss
 *      unter der ALTEN Verkettung stimmen (Zeilen-Hash über gespeichertem
 *      prev_hash, lückenlose Kette). Passt etwas nicht, bricht die Migration
 *      ab und schreibt NICHTS: das ist ein Befund für `audit:verify`, keine
 *      Reparaturaufgabe.
 *   2. Je Kette (Transaktion, Kopf gesperrt, in Blöcken): prev_hash/hash über
 *      der neuen Reihenfolge neu rechnen, Zeilen fortschreiben, Kopf setzen.
 *   3. Alte Tabellenkopf-Zeile entfernen.
 *
 * Die Nutzdaten bleiben unangetastet — `hashPayload()` ist unverändert, es
 * ändert sich ausschließlich die VERKETTUNG. Jede geänderte Zeile wird mit
 * Alt-/Neu-Hash als JSONL protokolliert
 * (storage/app/audit-chain-rechain-*.jsonl, GoBD-Nachweis). Die Datei
 * entsteht erst, wenn wirklich umgekettet wurde — auf leerer Datenbank
 * (migrate:fresh) bleibt sie aus (MVP-723).
 */
return new class extends Migration {
    private const CHUNK = 1000;

    public function up(): void {
        $proofPath = storage_path('app/audit-chain-rechain-' . now()->format('Ymd_His') . '.jsonl');

        /** @var array<string, class-string<HashChainable>> $chains */
        $chains = (array) config('audit.chains', []);

        foreach ($chains as $table => $modelClass) {
            if (! is_string($table) || ! is_string($modelClass) || ! is_a($modelClass, HashChainable::class, true)) {
                continue;
            }
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (DB::table($table)->exists()) {
                foreach ($this->scopeValues($table, $modelClass) as $scopeValue) {
                    $this->rechain($table, $modelClass, $scopeValue, $proofPath);
                }
            }

            // Der Tabellenkopf hat ausgedient; die Ketten-Köpfe stehen jetzt
            // je Organisation. Fehlende Köpfe legt der Schreibpfad selbst an.
            DB::table('audit_chain_heads')->where('chain', $table)->delete();
        }
    }

    /**
     * Beweisdurchlauf über die ALTE Tabellenkette und Sammlung der
     * vorkommenden Organisationswerte.
     *
     * @param  class-string<HashChainable>  $modelClass
     * @return list<int|null>
     */
    private function scopeValues(string $table, string $modelClass): array {
        $hasScope = Schema::hasColumn($table, 'organization_id');
        $expectedPrev = null;
        $values = [];

        foreach (DB::table($table)->orderBy('id')->cursor() as $row) {
            if ($row->prev_hash !== $expectedPrev) {
                throw new RuntimeException("[{$table}] prev_hash-Verkettung gebrochen bei id={$row->id} — keine Umkettung, audit:verify-Befund zuerst klären.");
            }

            $model = $modelClass::fromStorageRow((array) $row);
            $expected = $modelClass::chainHash($row->prev_hash, $model->hashPayload());
            if (! hash_equals($expected, (string) $row->hash)) {
                throw new RuntimeException("[{$table}] id={$row->id}: Zeilen-Hash stimmt nicht — mögliche echte Veränderung, Abbruch ohne Umkettung.");
            }

            $value = $hasScope ? (int) ($row->organization_id ?? 0) : null;
            $values[(string) $value] = $value;
            $expectedPrev = (string) $row->hash;
        }

        return array_values($values);
    }

    /**
     * Kettet EINE Organisation um: Kopf sperren, Zeilen blockweise in
     * id-Reihenfolge neu verketten, Kopf setzen.
     *
     * @param  class-string<HashChainable>  $modelClass
     */
    private function rechain(string $table, string $modelClass, ?int $scopeValue, string $proofPath): void {
        $chain = $scopeValue === null ? $table : $table . ':' . $scopeValue;

        /** @var list<string> $proof gepufferte Nachweiszeilen (siehe unten) */
        $proof = [];

        DB::transaction(function () use ($table, $modelClass, $scopeValue, $chain, $proofPath, &$proof): void {
            DB::table('audit_chain_heads')->insertOrIgnore(['chain' => $chain, 'head_hash' => null, 'height' => 0]);
            DB::table('audit_chain_heads')->where('chain', $chain)->lockForUpdate()->first();

            $prev = null;
            $rows = 0;
            $rewritten = 0;
            $lastId = 0;

            while (true) {
                $batch = DB::table($table)
                    ->when($scopeValue !== null, fn ($query) => $query->where(
                        fn ($scope) => $scope->where('organization_id', $scopeValue)
                            ->when($scopeValue === 0, fn ($zero) => $zero->orWhereNull('organization_id')),
                    ))
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->limit(self::CHUNK)
                    ->get();

                if ($batch->isEmpty()) {
                    break;
                }

                foreach ($batch as $row) {
                    $lastId = (int) $row->id;
                    $rows++;

                    $model = $modelClass::fromStorageRow((array) $row);
                    $newHash = $modelClass::chainHash($prev, $model->hashPayload());

                    if ($row->prev_hash !== $prev || ! hash_equals($newHash, (string) $row->hash)) {
                        DB::table($table)->where('id', $row->id)->update(['prev_hash' => $prev, 'hash' => $newHash]);
                        $proof[] = json_encode([
                            'chain' => $chain,
                            'id' => $row->id,
                            'reason' => 'rechained_per_organization',
                            'old_prev_hash' => $row->prev_hash,
                            'old_hash' => $row->hash,
                            'new_prev_hash' => $prev,
                            'new_hash' => $newHash,
                        ], JSON_UNESCAPED_SLASHES) . "\n";
                        $rewritten++;
                    }

                    $prev = $newHash;
                }
            }

            DB::table('audit_chain_heads')->where('chain', $chain)->update(['head_hash' => $prev, 'height' => $rows]);

            if ($rewritten > 0) {
                Log::notice('audit.chain_rechained', ['chain' => $chain, 'rows' => $rows, 'rewritten' => $rewritten, 'proof' => $proofPath]);
            }
        });

        // Nachweisdatei erst NACH dem Commit und nur bei echter Umkettung
        // (MVP-723): ohne Umkettung entsteht keine Datei, und ein Abbruch
        // (Rollback) hinterlässt keinen Nachweis über nie erfolgte Rewrites.
        if ($proof !== []) {
            file_put_contents($proofPath, implode('', $proof), FILE_APPEND | LOCK_EX);
        }
    }

    public function down(): void {
        // Die Umkettung ist nicht umkehrbar — der Nachweis liegt als JSONL in storage/app.
    }
};
