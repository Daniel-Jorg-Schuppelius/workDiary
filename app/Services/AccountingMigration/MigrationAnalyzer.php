<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MigrationAnalyzer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\AccountingMigration;

use App\Enums\Migration\{MigrationDataArea, MigrationProvider};
use App\Models\{Article, Customer, ExternalReference, LexofficeVoucher, OrgaMaxInvoice, Supplier};
use App\Models\Migration\{AccountingMigrationItem, AccountingMigrationRun};
use Illuminate\Database\Eloquent\Model;

/**
 * Analyse-/Dry-Run eines Buchhaltungswechsels (MVP-653): gleicht die
 * bestehenden Fremd-Referenzen beider Systeme an denselben lokalen
 * Fachobjekten ab und legt je Datensatz eine Migrationsposition an.
 *
 * Der Lauf schreibt AUSSCHLIESSLICH lokal — weder Lexoffice noch orgaMAX
 * werden berührt. Grundlage sind die bereits synchronisierten
 * {@see ExternalReference}-Einträge; WorkDiary bleibt damit die neutrale
 * Drehscheibe und kopiert nicht blind von Provider zu Provider.
 *
 * Ergebnis je Datensatz:
 *  - beide Fremd-IDs am selben Objekt → `matched` (nichts zu tun);
 *  - nur Quelle bekannt → `pending` (im Zielsystem aufzubauen);
 *  - Belege → `historic` (Historie, wird nie nachgebaut);
 *  - mehrdeutig/verlustbehaftet → `conflict` (Entscheidung nötig).
 */
class MigrationAnalyzer {
    /**
     * Führt die Analyse aus und liefert die Zählwerke je Datenbereich.
     *
     * @return array<string, array<string, int>>
     */
    public function run(AccountingMigrationRun $run): array {
        $counters = [];
        foreach ($run->areas() as $area) {
            $counters[$area->value] = $area === MigrationDataArea::Documents
                ? $this->analyzeDocuments($run)
                : $this->analyzeMasterData($run, $area);
        }

        return $counters;
    }

    /**
     * Stammdaten (Kunden, Lieferanten, Artikel): Quell-Referenzen des
     * Quellsystems durchgehen und gegen die Ziel-Referenz am selben lokalen
     * Objekt prüfen.
     *
     * @return array<string, int>
     */
    private function analyzeMasterData(AccountingMigrationRun $run, MigrationDataArea $area): array {
        $counters = ['read' => 0, 'matched' => 0, 'pending' => 0, 'conflict' => 0];

        $sourceRefs = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $run->organization_id)
            ->where('plugin_id', $run->source_plugin)
            ->where('external_type', $run->source()->externalTypeFor($area))
            ->where('referenceable_type', $this->morphClassFor($area, $run->source()))
            ->get();

        foreach ($sourceRefs as $ref) {
            $counters['read']++;
            $local = $ref->referenceable;
            if (! $local instanceof Model) {
                // Verwaiste Referenz: das lokale Objekt existiert nicht mehr.
                $this->upsertItem($run, $area, [
                    'status' => AccountingMigrationItem::STATUS_CONFLICT,
                    'source_external_id' => $ref->external_id,
                    'dedupe_key' => $area->value . ':' . $ref->external_id,
                    'display_title' => (string) $ref->external_id,
                    'note' => (string) __('Die Quellreferenz zeigt auf kein lokales Objekt mehr.'),
                ]);
                $counters['conflict']++;

                continue;
            }

            $targetRef = ExternalReference::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $run->organization_id)
                ->where('plugin_id', $run->target_plugin)
                ->where('external_type', $run->target()->externalTypeFor($area))
                ->where('referenceable_type', $local->getMorphClass())
                ->where('referenceable_id', $local->getKey())
                ->first();

            $status = $targetRef !== null
                ? AccountingMigrationItem::STATUS_MATCHED
                : AccountingMigrationItem::STATUS_PENDING;

            $this->upsertItem($run, $area, [
                'status' => $status,
                'source_external_id' => $ref->external_id,
                'target_external_id' => $targetRef?->external_id,
                'referenceable_type' => $local->getMorphClass(),
                'referenceable_id' => $local->getKey(),
                'dedupe_key' => $area->value . ':' . $ref->external_id,
                'display_title' => $this->titleFor($local),
                'source_snapshot' => (array) ($ref->payload ?? []),
            ]);

            $counters[$status === AccountingMigrationItem::STATUS_MATCHED ? 'matched' : 'pending']++;
        }

        return $counters;
    }

    /**
     * Belege: bleiben read-only Historie im Quellsystem. Sie werden NIE als
     * Zielbelege nachgebaut — erfasst wird nur, welche offen sind und
     * deshalb bis zum Abschluss beobachtet werden müssen.
     *
     * @return array<string, int>
     */
    private function analyzeDocuments(AccountingMigrationRun $run): array {
        $counters = ['read' => 0, 'historic' => 0, 'open' => 0];

        foreach ($this->sourceDocuments($run) as $document) {
            $counters['read']++;
            $counters['historic']++;
            if ($document['is_open']) {
                $counters['open']++;
            }

            $this->upsertItem($run, MigrationDataArea::Documents, [
                'status' => AccountingMigrationItem::STATUS_HISTORIC,
                'source_external_id' => $document['external_id'],
                'dedupe_key' => 'documents:' . $document['external_id'],
                'display_title' => (string) ($document['number'] ?? $document['external_id']),
                'source_snapshot' => $document,
                'note' => $document['is_open']
                    ? (string) __('Offener Altbeleg — wird im Quellsystem zu Ende geführt.')
                    : null,
            ]);
        }

        return $counters;
    }

    /**
     * Beleghistorie des QUELLSYSTEMS — richtungsabhängig: Lexoffice führt
     * einen eigenen Belegspiegel (`lexoffice_vouchers`), orgaMAX legt seine
     * Belegprojektion als {@see ExternalReference}-Payload ab.
     *
     * @return iterable<int, array{external_id: string, number: ?string, status: ?string, date: ?string, open_amount: ?float, is_open: bool}>
     */
    private function sourceDocuments(AccountingMigrationRun $run): iterable {
        $provider = $run->source();
        $settled = $provider->settledDocumentStates();

        if ($provider === MigrationProvider::Lexoffice) {
            foreach (LexofficeVoucher::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $run->organization_id)
                ->orderBy('id')
                ->cursor() as $voucher) {
                $status = (string) $voucher->voucher_status;
                yield [
                    'external_id' => (string) $voucher->external_id,
                    'number' => $voucher->voucher_number,
                    'status' => $status,
                    'date' => $voucher->voucher_date?->toDateString(),
                    'open_amount' => $voucher->open_amount?->toFloat(),
                    'is_open' => ! (bool) $voucher->archived && ! in_array($status, $settled, true),
                ];
            }

            return;
        }

        foreach (OrgaMaxInvoice::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $run->organization_id)
            ->orderBy('id')
            ->cursor() as $invoice) {
            $status = (string) $invoice->invoice_status;
            yield [
                'external_id' => (string) $invoice->external_id,
                'number' => $invoice->invoice_number,
                'status' => $status,
                'date' => $invoice->invoice_date?->toDateString(),
                'open_amount' => $invoice->outstanding_amount?->toFloat(),
                'is_open' => ! in_array($status, $settled, true),
            ];
        }
    }

    /**
     * Offene Altbelege des Quellsystems — sie verhindern den Abschluss,
     * solange sie nicht ausgeglichen sind.
     */
    public function openSourceDocuments(AccountingMigrationRun $run): int {
        if (! $run->coversArea(MigrationDataArea::Documents)) {
            return 0;
        }

        $open = 0;
        foreach ($this->sourceDocuments($run) as $document) {
            if ($document['is_open']) {
                $open++;
            }
        }

        return $open;
    }

    /**
     * Position idempotent anlegen/aktualisieren. Bereits entschiedene
     * Positionen bleiben unangetastet — ein Wiederholungslauf darf eine
     * getroffene Entscheidung nie überschreiben.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function upsertItem(AccountingMigrationRun $run, MigrationDataArea $area, array $attributes): AccountingMigrationItem {
        $existing = AccountingMigrationItem::query()
            ->withoutGlobalScopes()
            ->where('accounting_migration_run_id', $run->id)
            ->where('dedupe_key', $attributes['dedupe_key'])
            ->first();

        if ($existing !== null) {
            if ($existing->decided_at !== null) {
                return $existing;
            }
            $existing->forceFill($attributes)->save();

            return $existing;
        }

        return AccountingMigrationItem::create($attributes + [
            'organization_id' => $run->organization_id,
            'accounting_migration_run_id' => $run->id,
            'data_area' => $area->value,
        ]);
    }

    /** Morph-Klasse des lokalen Zielmodells eines Datenbereichs. */
    private function morphClassFor(MigrationDataArea $area, MigrationProvider $provider): string {
        return match ($area) {
            MigrationDataArea::Customers => (new Customer)->getMorphClass(),
            MigrationDataArea::Suppliers => (new Supplier)->getMorphClass(),
            MigrationDataArea::Articles => (new Article)->getMorphClass(),
            // Belege laufen nie über analyzeMasterData(); der Spiegel ist
            // richtungsabhängig (siehe sourceDocuments()).
            MigrationDataArea::Documents => $provider === MigrationProvider::Lexoffice
                ? (new LexofficeVoucher)->getMorphClass()
                : (new OrgaMaxInvoice)->getMorphClass(),
        };
    }

    private function titleFor(Model $model): string {
        foreach (['name', 'company', 'number', 'title'] as $attribute) {
            $value = trim((string) ($model->getAttribute($attribute) ?? ''));
            if ($value !== '') {
                return mb_substr($value, 0, 191);
            }
        }

        return class_basename($model) . ' #' . $model->getKey();
    }
}
