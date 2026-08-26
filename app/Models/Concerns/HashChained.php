<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HashChained.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Concerns;

use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Macht ein Audit-Modell revisionssicher (GoBD): jede Zeile ist über eine
 * SHA-256-Hash-Kette mit ihrer Vorgängerzeile verbunden
 *   hash = SHA-256( prev_hash | kanonische Nutzdaten )
 * und das Modell ist append-only ({@see AppendOnly}: UPDATE/DELETE werfen).
 *
 * Nebenläufigkeit: Der Kettenkopf liegt in `audit_chain_heads` (eine Zeile pro
 * Kette). Beim Insert wird diese Zeile per `lockForUpdate` gesperrt, der Hash
 * berechnet, die Audit-Zeile geschrieben und der Kopf fortgeschrieben – alles
 * in einer Transaktion. Zwei gleichzeitige Inserts können so nicht denselben
 * prev_hash erhalten (kein „Fork" der Kette).
 *
 * Eine Kette je ORGANISATION (`tabelle:organisation`, {@see chainName()}):
 * Mit einer Kette je Tabelle sperrten alle Mandanten denselben Kopf —
 * gemessen mit `audit:measure-chain-contention` verklemmten dabei 445 von 900
 * gleichzeitigen Einfügungen (MVP-722, Vollscan 2026-08-23 A5).
 *
 * Nutzende Modelle implementieren {@see hashPayload()} (welche Felder in den
 * Hash eingehen); {@see chainScopeColumn()} nur, wenn die Kette NICHT je
 * Organisation geführt werden soll.
 *
 * @phpstan-consistent-constructor
 *
 * @method static newFromBuilder(array<string, mixed> $attributes = [], string|null $connection = null)
 */
trait HashChained {
    use AppendOnly;

    /**
     * @return array<string, mixed> Nutzdaten dieser Zeile in fester Reihenfolge.
     */
    abstract public function hashPayload(): array;

    /**
     * Name der Hash-Kette (eine Zeile in audit_chain_heads):
     * `tabelle:organisation`.
     *
     * Eine Kette je TABELLE hätte den Kettenkopf zum globalen Nadelöhr
     * gemacht — gemessen mit `audit:measure-chain-contention` (3 Organisationen
     * × 300 Zeilen): 2,40 ms/Zeile allein gegen 6,04 ms/Zeile parallel, dabei
     * 445 von 900 Einfügungen als Verklemmung abgebrochen. Je Organisation
     * eine Kette trennt die Sperren; die Beweiskraft bleibt unverändert, weil
     * `organization_id` ohnehin in jedem hashPayload() steckt.
     *
     * Modelle ohne Organisationsbezug behalten die Tabellenkette
     * ({@see chainScopeColumn()}); ein leeres Feld ergibt `tabelle:0`
     * (plattformweite Ereignisse bilden damit ihre eigene Kette).
     */
    public function chainName(): string {
        $column = $this->chainScopeColumn();
        if ($column === null) {
            return $this->getTable();
        }

        return $this->getTable() . ':' . (int) ($this->getAttribute($column) ?? 0);
    }

    /**
     * Spalte, die die Kette aufteilt — `null` = eine Kette je Tabelle.
     * Standard ist `organization_id`, sofern das Modell sie führt.
     */
    protected function chainScopeColumn(): ?string {
        return in_array('organization_id', $this->getFillable(), true) ? 'organization_id' : null;
    }

    /**
     * Verkettet als LETZTER creating-Listener: attributmutierende Traits
     * (z. B. BelongsToOrganization-Auto-Befüllung) müssen vor HashChained
     * deklariert sein, sonst ginge ihr Wert nicht in den Hash ein — die Zeile
     * wäre ab Geburt unprüfbar (Regressionstest AuditLogChainTest).
     */
    public static function bootHashChained(): void {
        static::creating(static function (self $model): void {
            $model->applyChainHash();
        });
    }

    /**
     * Sperrt den Kettenkopf und setzt prev_hash/hash. Läuft im creating-Event —
     * also innerhalb der Transaktion von {@see performInsert()}.
     */
    protected function applyChainHash(): void {
        $this->prepareHashTimestamps();

        $head = DB::table('audit_chain_heads')->where('chain', $this->chainName())->lockForUpdate()->first();
        $prevHash = $head->head_hash ?? null;

        $this->setAttribute('prev_hash', $prevHash);
        $this->setAttribute('hash', static::chainHash($prevHash, $this->hashPayload()));
    }

    /**
     * Serialisierter Insert: Transaktion um Hash-Berechnung (creating-Event,
     * {@see applyChainHash()}), Insert und Kopf-Fortschreibung.
     */
    protected function performInsert(Builder $query) {
        $connection = $this->getConnection();
        $chain = $this->chainName();

        // Kopfzeile garantiert vorhanden (idempotent, ohne den Lock zu halten).
        DB::table('audit_chain_heads')->insertOrIgnore(['chain' => $chain, 'head_hash' => null, 'height' => 0]);

        return $connection->transaction(function () use ($query, $chain) {
            $inserted = parent::performInsert($query);

            if ($inserted !== false) {
                DB::table('audit_chain_heads')->where('chain', $chain)->update([
                    'head_hash' => $this->getAttribute('hash'),
                    'height' => DB::raw('height + 1'),
                ]);
            }

            return $inserted;
        });
    }

    /**
     * Fixiert created_at vor der Hash-Berechnung, falls noch nicht gesetzt
     * (das `creating`-Event feuert vor Eloquents updateTimestamps()).
     */
    protected function prepareHashTimestamps(): void {
        if ($this->getAttribute('created_at') === null) {
            $now = $this->freshTimestamp();
            $this->setAttribute('created_at', $now);
            if ($this->usesTimestamps()) {
                $this->setAttribute('updated_at', $now);
            }
        }
    }

    /**
     * Formatiert created_at deterministisch für den Hash.
     */
    protected function hashCreatedAt(): string {
        $createdAt = $this->getAttribute('created_at');

        return $createdAt instanceof DateTimeInterface
            ? $createdAt->format('Y-m-d H:i:s')
            : (string) $createdAt;
    }

    /**
     * Deterministische Serialisierung der Nutzdaten. Skalare/Arrays werden
     * stabil normalisiert; JSON-Strings (Roh-Backfill) werden zuvor dekodiert,
     * damit Modell- und Migrationspfad denselben Hash erzeugen.
     *
     * @param array<string, mixed> $data
     */
    public static function canonicalPayload(array $data): string {
        $normalized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded))) {
                    $value = $decoded;
                }
            }
            self::assertScalarPayloadValue($key, $value);
            $normalized[$key] = $value;
        }

        return JsonHelper::encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Wirft, wenn ein Payload-Wert (auch verschachtelt) ein Objekt ist.
     */
    private static function assertScalarPayloadValue(string $key, mixed $value): void {
        if (is_object($value)) {
            throw new InvalidArgumentException(
                "Hash-Payload [{$key}] ist ein Objekt (" . $value::class . ') — Rohwert statt Cast übergeben.',
            );
        }
        if (is_array($value)) {
            array_walk_recursive($value, static function (mixed $leaf) use ($key): void {
                if (is_object($leaf)) {
                    throw new InvalidArgumentException(
                        "Hash-Payload [{$key}] enthält verschachtelt ein Objekt (" . $leaf::class . ') — Rohwert statt Cast übergeben.',
                    );
                }
            });
        }
    }

    /**
     * SHA-256 über prev_hash und kanonische Nutzdaten.
     *
     * @param array<string, mixed> $data
     */
    public static function chainHash(?string $prevHash, array $data): string {
        return CryptoHelper::hash((string) $prevHash . '|' . self::canonicalPayload($data));
    }

    /**
     * Hydriert ein Modell aus einer Roh-DB-Zeile (Casts greifen beim Lesen).
     *
     * @param array<string, mixed> $attributes
     */
    public static function fromStorageRow(array $attributes): static {
        return (new static)->newFromBuilder($attributes);
    }
}
