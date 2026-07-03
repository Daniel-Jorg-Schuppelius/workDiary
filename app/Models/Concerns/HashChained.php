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
use RuntimeException;

/**
 * Macht ein Audit-Modell revisionssicher (GoBD): jede Zeile ist über eine
 * SHA-256-Hash-Kette mit ihrer Vorgängerzeile verbunden
 *   hash = SHA-256( prev_hash | kanonische Nutzdaten )
 * und das Modell ist append-only (UPDATE/DELETE werfen).
 *
 * Nebenläufigkeit: Der Kettenkopf liegt in `audit_chain_heads` (eine Zeile pro
 * Kette). Beim Insert wird diese Zeile per `lockForUpdate` gesperrt, der Hash
 * berechnet, die Audit-Zeile geschrieben und der Kopf fortgeschrieben – alles
 * in einer Transaktion. Zwei gleichzeitige Inserts können so nicht denselben
 * prev_hash erhalten (kein „Fork" der Kette).
 *
 * Nutzende Modelle implementieren {@see hashPayload()} (welche Felder in den
 * Hash eingehen) und {@see chainName()} (Name der Kette = i. d. R. Tabellenname).
 *
 * @phpstan-consistent-constructor
 *
 * @method static void updating(\Closure $callback)
 * @method static void deleting(\Closure $callback)
 * @method static newFromBuilder(array<string, mixed> $attributes = [], string|null $connection = null)
 */
trait HashChained {
    public static function bootHashChained(): void {
        static::updating(function (): void {
            throw new RuntimeException(static::class . ' ist append-only und darf nicht geändert werden.');
        });
        static::deleting(function (): void {
            throw new RuntimeException(static::class . ' ist append-only und darf nicht gelöscht werden.');
        });
    }

    /**
     * @return array<string, mixed> Nutzdaten dieser Zeile in fester Reihenfolge.
     */
    abstract public function hashPayload(): array;

    /**
     * Name der Hash-Kette (eine Zeile in audit_chain_heads).
     */
    public function chainName(): string {
        return $this->getTable();
    }

    /**
     * Serialisierter Insert: sperrt den Kettenkopf, verkettet und schreibt fort.
     */
    protected function performInsert(Builder $query) {
        $this->prepareHashTimestamps();

        $connection = $this->getConnection();
        $chain = $this->chainName();

        // Kopfzeile garantiert vorhanden (idempotent, ohne den Lock zu halten).
        DB::table('audit_chain_heads')->insertOrIgnore(['chain' => $chain, 'head_hash' => null, 'height' => 0]);

        return $connection->transaction(function () use ($query, $chain) {
            $head = DB::table('audit_chain_heads')->where('chain', $chain)->lockForUpdate()->first();
            $prevHash = $head->head_hash ?? null;

            $this->setAttribute('prev_hash', $prevHash);
            $this->setAttribute('hash', static::chainHash($prevHash, $this->hashPayload()));

            $inserted = parent::performInsert($query);

            if ($inserted !== false) {
                DB::table('audit_chain_heads')->where('chain', $chain)->update([
                    'head_hash' => $this->getAttribute('hash'),
                    'height' => (int) ($head->height ?? 0) + 1,
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
            $normalized[$key] = $value;
        }

        return JsonHelper::encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
