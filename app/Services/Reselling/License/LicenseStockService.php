<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseStockService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\License;

use App\Enums\Reselling\{LicenseAssignmentEnd, LicenseUnitStatus};
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Platform\{Organization, User};
use App\Models\Reselling\{ResaleLicenseAssignment, ResaleLicenseBatch, ResaleLicenseKey, ResaleLicenseProduct, ResaleLicenseUnit};
use App\Services\Billing\Purchase\PurchaseDocument;
use App\Support\Crypto\BlindIndex;
use App\Support\Toolkit\CsvFacade;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\CSV\StringHelper as CsvStringHelper;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\{Cache, Crypt, DB};

/**
 * Lizenzbestand aus Einkaufspaketen (Feature 152, MVP-1024): die einzige
 * Schreibstelle für Pakete, Schlüssel, Verkäufe, Rücknahmen und Sperren.
 *
 * Jede Mutation einer Lizenz sperrt zuerst deren Zeile und prüft danach den
 * Zustand erneut; der Unique-Index auf `active_unit_id` ist das Netz darunter.
 * Bestände werden gezählt, nie als Restzähler geführt.
 */
class LicenseStockService {
    public const MAX_KEY_ROLES = 5;

    public const MAX_BATCH_QUANTITY = 5000;

    public const MAX_KEY_LENGTH = 500;

    private const IMPORT_TTL_MINUTES = 30;

    /** @param  array{name: string, manufacturer?: string|null, key_labels: list<string|null>, reorder_level?: int|null, article_ref?: string|null, note?: string|null}  $data */
    public function saveProduct(Organization $organization, ?ResaleLicenseProduct $product, array $data, User $actor): ResaleLicenseProduct {
        $roles = [];
        foreach ($data['key_labels'] as $label) {
            $label = trim((string) $label);
            if ($label !== '') {
                $roles[] = ['code' => 'key_' . (count($roles) + 1), 'label' => $label];
            }
        }
        if ($roles === []) {
            throw new LicenseStockException('roles_missing', 'key_labels');
        }

        $product ??= new ResaleLicenseProduct(['organization_id' => $organization->id, 'created_by' => $actor->id]);
        $product->fill([
            'name' => trim($data['name']),
            'manufacturer' => $data['manufacturer'] ?? null,
            'key_roles' => array_slice($roles, 0, self::MAX_KEY_ROLES),
            'reorder_level' => $data['reorder_level'] ?? null,
            'article_ref' => $data['article_ref'] ?? null,
            'note' => $data['note'] ?? null,
            'updated_by' => $actor->id,
        ])->save();

        return $product;
    }

    /**
     * Paket mit so vielen leeren, nummerierten Lizenzen wie gekauft.
     *
     * @param  array{reference: string, purchased_on: string, quantity: int, supplier_id?: int|null, supplier_name?: string|null, note?: string|null}  $data
     */
    public function createBatch(ResaleLicenseProduct $product, array $data, ?PurchaseDocument $document, User $actor): ResaleLicenseBatch {
        $quantity = (int) $data['quantity'];
        if ($quantity < 1 || $quantity > self::MAX_BATCH_QUANTITY) {
            throw new LicenseStockException('quantity', 'quantity', ['max' => self::MAX_BATCH_QUANTITY]);
        }
        $roles = $product->keyRoles();

        return DB::transaction(function () use ($product, $data, $document, $actor, $quantity, $roles): ResaleLicenseBatch {
            $batch = ResaleLicenseBatch::query()->create([
                'organization_id' => $product->organization_id,
                'product_id' => $product->id,
                'reference' => trim($data['reference']),
                'purchased_on' => $data['purchased_on'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'supplier_name' => ($data['supplier_id'] ?? null) === null ? ($data['supplier_name'] ?? null) : null,
                'quantity' => $quantity,
                'key_roles' => $roles,
                'key_count' => count($roles),
                'document_type' => $document?->morphClass,
                'document_id' => $document?->morphId,
                'document_reference' => $document !== null ? Str::limit($document->reference(), 117) : null,
                'note' => $data['note'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $now = CarbonImmutable::now();
            $rows = [];
            for ($position = 1; $position <= $quantity; $position++) {
                $rows[] = ['organization_id' => $batch->organization_id, 'batch_id' => $batch->id, 'position' => $position, 'created_at' => $now, 'updated_at' => $now];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                ResaleLicenseUnit::query()->insert($chunk);
            }

            return $batch;
        });
    }

    /** Nur ein irrtümlich angelegtes Paket: ohne Schlüssel, Sperre und Verkaufshistorie. */
    public function deleteBatch(ResaleLicenseBatch $batch): void {
        DB::transaction(function () use ($batch): void {
            $units = ResaleLicenseUnit::query()->where('batch_id', $batch->id)->select('id');
            $used = $batch->units()->whereNotNull('blocked_at')->exists()
                || ResaleLicenseKey::query()->whereIn('unit_id', $units)->exists()
                || ResaleLicenseAssignment::query()->whereIn('unit_id', $units)->exists();
            if ($used) {
                throw new LicenseStockException('batch_in_use');
            }
            $batch->delete();
        });
    }

    /**
     * Schlüssel einer freien Lizenz setzen, ändern oder entfernen. Ändern und
     * Entfernen brauchen einen Grund; der Wert erscheint weder im Audit noch
     * in einer Meldung.
     *
     * @param  array<string, string|null>  $set  Rolle → neuer Wert (leer = unverändert)
     * @param  list<string>  $remove  Rollen, deren Schlüssel entfernt wird
     * @return int Anzahl geänderter Rollen
     */
    public function saveKeys(ResaleLicenseUnit $unit, array $set, array $remove, ?string $reason, User $actor): int {
        return DB::transaction(function () use ($unit, $set, $remove, $reason, $actor): int {
            $unit = $this->lockUnit($unit);
            if ($unit->activeAssignment !== null) {
                throw new LicenseStockException('keys_sold');
            }

            $roles = array_column($unit->batch->keyRoles(), 'code');
            /** @var Collection<string, ResaleLicenseKey> $existing */
            $existing = $unit->keys()->get()->keyBy('role');
            $changes = [];
            $writes = [];
            foreach ($remove as $role) {
                if (! in_array($role, $roles, true)) {
                    throw new LicenseStockException('unknown_role');
                }
                if ($existing->has($role)) {
                    $changes[$role] = 'removed';
                }
            }
            foreach ($set as $role => $value) {
                if (! in_array($role, $roles, true)) {
                    throw new LicenseStockException('unknown_role');
                }
                $value = trim((string) $value);
                if ($value === '' || isset($changes[$role])) {
                    continue;
                }
                $field = 'license_keys.' . $role;
                if (mb_strlen($value) > self::MAX_KEY_LENGTH) {
                    throw new LicenseStockException('key_too_long', $field, ['max' => self::MAX_KEY_LENGTH]);
                }
                $fingerprint = self::fingerprint($value);
                $current = $existing->get($role);
                if ($current !== null && hash_equals($current->fingerprint, $fingerprint)) {
                    continue;
                }
                $this->assertUniqueKey($unit, $role, $fingerprint, $field);
                $changes[$role] = $current !== null ? 'changed' : 'added';
                $writes[$role] = [$value, $fingerprint];
            }

            if (array_intersect($changes, ['changed', 'removed']) !== [] && trim((string) $reason) === '') {
                throw new LicenseStockException('reason_required', 'reason');
            }

            foreach ($changes as $role => $change) {
                if ($change === 'removed') {
                    $existing->get($role)?->delete();

                    continue;
                }
                [$value, $fingerprint] = $writes[$role];
                $this->writeKey($unit, $role, $value, $fingerprint, $existing->get($role), $actor);
            }

            if ($changes !== []) {
                $unit->audit('resale_license.keys_changed', ['roles' => $changes, 'reason' => trim((string) $reason) ?: null]);
            }

            return count($changes);
        });
    }

    /**
     * CSV prüfen und — nur wenn fehlerfrei — für die Übernahme vormerken. Die
     * Datei liegt verschlüsselt und nutzergebunden im Cache, nie als Klartext.
     *
     * @return array{token: string|null, rows: list<array{line: int, position: int, added: int, complete: bool}>, errors: list<array{line: int, field: string, message: string}>, changes: int}
     */
    public function previewImport(ResaleLicenseBatch $batch, string $csv, User $actor): array {
        $analysis = $this->analyzeImport($batch, $csv);
        $token = null;
        if ($analysis['errors'] === [] && $analysis['writes'] !== []) {
            $token = Str::random(32);
            Cache::put($this->importCacheKey($batch, $actor, $token), Crypt::encryptString($csv), CarbonImmutable::now()->addMinutes(self::IMPORT_TTL_MINUTES));
        }

        return ['token' => $token, 'rows' => $analysis['rows'], 'errors' => $analysis['errors'], 'changes' => count($analysis['writes'])];
    }

    /** @return int Anzahl übernommener Schlüssel */
    public function confirmImport(ResaleLicenseBatch $batch, string $token, User $actor): int {
        $stored = Cache::pull($this->importCacheKey($batch, $actor, $token));
        if (! is_string($stored)) {
            throw new LicenseStockException('import_expired');
        }
        $csv = Crypt::decryptString($stored);

        return DB::transaction(function () use ($batch, $csv, $actor): int {
            ResaleLicenseUnit::query()->where('batch_id', $batch->id)->lockForUpdate()->get(['id']);
            $analysis = $this->analyzeImport($batch, $csv);
            if ($analysis['errors'] !== []) {
                throw new LicenseStockException('import_invalid');
            }

            $units = $batch->units()->get()->keyBy('id');
            foreach ($analysis['writes'] as [$unitId, $role, $value, $fingerprint]) {
                $unit = $units->get($unitId) ?? throw new LicenseStockException('import_invalid');
                $this->writeKey($unit, $role, $value, $fingerprint, null, $actor);
            }
            $batch->audit('resale_license.keys_imported', ['keys' => count($analysis['writes'])]);

            return count($analysis['writes']);
        });
    }

    public function importTemplate(ResaleLicenseBatch $batch): string {
        $headers = array_merge(['position'], array_column($batch->keyRoles(), 'code'));
        $rows = [];
        for ($position = 1; $position <= $batch->quantity; $position++) {
            $rows[] = ['position' => (string) $position];
        }

        return CsvFacade::buildCsv($headers, $rows);
    }

    /**
     * Genau eine verfügbare Lizenz an einen Kunden. Ein wiederholtes Absenden
     * mit demselben Token liefert den bestehenden Verkauf zurück.
     */
    public function sell(ResaleLicenseUnit $unit, Customer $customer, ?ForeignCustomer $foreign, CarbonImmutable $soldOn, ?string $invoiceReference, ?string $token, User $actor): ResaleLicenseAssignment {
        $this->assertHolder($unit, $customer, $foreign);

        try {
            return DB::transaction(function () use ($unit, $customer, $foreign, $soldOn, $invoiceReference, $token, $actor): ResaleLicenseAssignment {
                $previous = $this->byToken($unit, $token);
                if ($previous !== null) {
                    return $previous;
                }
                $unit = $this->lockUnit($unit);
                if ($unit->status() !== LicenseUnitStatus::Available) {
                    throw new LicenseStockException('not_available');
                }

                $assignment = $this->assign($unit, $customer, $foreign, $soldOn, $invoiceReference, $token, $actor);
                $unit->audit('resale_license.sold', ['customer_id' => $customer->id, 'foreign_customer_id' => $foreign?->id, 'sold_on' => $soldOn->toDateString()]);

                return $assignment;
            });
        } catch (UniqueConstraintViolationException) {
            // Gleichzeitiger Verkauf: der zweite scheitert am Index — derselbe Token ist dann Wiederholung.
            return $this->byToken($unit, $token) ?? throw new LicenseStockException('not_available');
        }
    }

    /** Falscher Kunde: die Lizenz wechselt nahtlos den Kunden, beide bleiben in der Historie. */
    public function reassign(ResaleLicenseAssignment $assignment, Customer $customer, ?ForeignCustomer $foreign, string $reason, User $actor): ResaleLicenseAssignment {
        $this->assertReason($reason);
        $this->assertHolder($assignment->unit, $customer, $foreign);

        return DB::transaction(function () use ($assignment, $customer, $foreign, $reason, $actor): ResaleLicenseAssignment {
            $unit = $this->lockUnit($assignment->unit);
            $current = $unit->activeAssignment;
            if ($current === null || $current->id !== $assignment->id) {
                throw new LicenseStockException('assignment_ended');
            }
            if ($current->customer_id === $customer->id && $current->foreign_customer_id === $foreign?->id) {
                throw new LicenseStockException('same_holder', 'customer_id');
            }

            $this->end($current, LicenseAssignmentEnd::Corrected, $reason, $actor);
            $next = $this->assign($unit, $customer, $foreign, $current->sold_on, $current->invoice_reference, null, $actor);
            $unit->audit('resale_license.reassigned', [
                'from' => ['customer_id' => $current->customer_id, 'foreign_customer_id' => $current->foreign_customer_id],
                'to' => ['customer_id' => $customer->id, 'foreign_customer_id' => $foreign?->id],
                'reason' => $reason,
            ]);

            return $next;
        });
    }

    /** Rücknahme: Zuordnung endet, die Lizenz wird im selben Schritt gesperrt — nie frei. */
    public function returnSale(ResaleLicenseAssignment $assignment, string $reason, User $actor): void {
        $this->assertReason($reason);

        DB::transaction(function () use ($assignment, $reason, $actor): void {
            $unit = $this->lockUnit($assignment->unit);
            $current = $unit->activeAssignment;
            if ($current === null || $current->id !== $assignment->id) {
                throw new LicenseStockException('assignment_ended');
            }

            $this->end($current, LicenseAssignmentEnd::Returned, $reason, $actor);
            $unit->forceFill(['blocked_at' => CarbonImmutable::now(), 'blocked_reason' => Str::limit($reason, 252), 'blocked_by_user_id' => $actor->id])->save();
            $unit->audit('resale_license.returned', ['customer_id' => $current->customer_id, 'reason' => $reason]);
        });
    }

    public function block(ResaleLicenseUnit $unit, string $reason, User $actor): void {
        $this->assertReason($reason);

        DB::transaction(function () use ($unit, $reason, $actor): void {
            $unit = $this->lockUnit($unit);
            if ($unit->activeAssignment !== null) {
                throw new LicenseStockException('block_sold');
            }
            if ($unit->blocked_at !== null) {
                throw new LicenseStockException('already_blocked');
            }
            $unit->forceFill(['blocked_at' => CarbonImmutable::now(), 'blocked_reason' => Str::limit($reason, 252), 'blocked_by_user_id' => $actor->id])->save();
            $unit->audit('resale_license.blocked', ['reason' => $reason]);
        });
    }

    /** Sperre aufheben nur mit bestätigter Wiederverwendbarkeit; das System prüft keine Herstelleraktivierung. */
    public function unblock(ResaleLicenseUnit $unit, string $reason, bool $confirmed, User $actor): void {
        $this->assertReason($reason);
        if (! $confirmed) {
            throw new LicenseStockException('unblock_unconfirmed', 'confirmed');
        }

        DB::transaction(function () use ($unit, $reason): void {
            $unit = $this->lockUnit($unit);
            if ($unit->blocked_at === null || $unit->activeAssignment !== null) {
                throw new LicenseStockException('not_blocked');
            }
            $previous = $unit->blocked_reason;
            $unit->forceFill(['blocked_at' => null, 'blocked_reason' => null, 'blocked_by_user_id' => null])->save();
            $unit->audit('resale_license.unblocked', ['reason' => $reason, 'blocked_reason' => $previous]);
        });
    }

    /**
     * Klartext des Schlüsselsatzes für die geschützte Anzeige; der Zugriff wird
     * ohne Werte protokolliert.
     *
     * @return list<array{role: string, label: string, value: string|null}>
     */
    public function revealKeys(ResaleLicenseUnit $unit): array {
        $keys = $unit->keys()->get()->keyBy('role');
        $unit->audit('resale_license.keys_viewed', ['roles' => $keys->keys()->all()]);

        return array_map(static fn (array $role): array => [
            'role' => $role['code'],
            'label' => $role['label'],
            'value' => $keys->get($role['code'])?->value,
        ], $unit->batch->keyRoles());
    }

    /** Vorschlag im Verkaufsdialog: die älteste verfügbare Lizenz (Kaufdatum, Paket, Position). */
    public function nextAvailable(ResaleLicenseProduct $product): ?ResaleLicenseUnit {
        return ResaleLicenseUnit::query()
            ->available()
            ->join('resale_license_batches as sb', 'sb.id', '=', 'resale_license_units.batch_id')
            ->where('sb.product_id', $product->id)
            ->orderBy('sb.purchased_on')
            ->orderBy('sb.id')
            ->orderBy('resale_license_units.position')
            ->select('resale_license_units.*')
            ->first();
    }

    /**
     * Aktueller Bestand je Paket — unabhängig vom Header-Zeitraum.
     *
     * @param  array<int, int|string>|null  $batchIds
     * @return Collection<int, array{purchased: int, available: int, sold: int, incomplete: int, blocked: int}>
     */
    public function batchCounts(Organization $organization, ?array $batchIds = null): Collection {
        $keyCounts = DB::table('resale_license_keys')
            ->where('organization_id', $organization->id)
            ->selectRaw('unit_id, COUNT(*) AS cnt')
            ->groupBy('unit_id');

        return DB::table('resale_license_units as u')
            ->join('resale_license_batches as b', 'b.id', '=', 'u.batch_id')
            ->leftJoin('resale_license_assignments as a', 'a.active_unit_id', '=', 'u.id')
            ->leftJoinSub($keyCounts, 'k', 'k.unit_id', '=', 'u.id')
            ->where('u.organization_id', $organization->id)
            ->when($batchIds !== null, static fn ($q) => $q->whereIn('u.batch_id', $batchIds))
            ->groupBy('u.batch_id')
            ->selectRaw('u.batch_id, COUNT(*) AS purchased')
            ->selectRaw('SUM(CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END) AS sold')
            ->selectRaw('SUM(CASE WHEN a.id IS NULL AND u.blocked_at IS NOT NULL THEN 1 ELSE 0 END) AS blocked')
            ->selectRaw('SUM(CASE WHEN a.id IS NULL AND u.blocked_at IS NULL AND COALESCE(k.cnt, 0) >= b.key_count THEN 1 ELSE 0 END) AS available')
            ->get()
            ->mapWithKeys(static function (object $row): array {
                $purchased = (int) $row->purchased;
                $sold = (int) $row->sold;
                $blocked = (int) $row->blocked;
                $available = (int) $row->available;

                return [(int) $row->batch_id => [
                    'purchased' => $purchased,
                    'available' => $available,
                    'sold' => $sold,
                    'incomplete' => $purchased - $sold - $blocked - $available,
                    'blocked' => $blocked,
                ]];
            });
    }

    /**
     * Bestand je Produkt über alle Pakete, mit Nachbestellhinweis.
     *
     * @param  Collection<int, ResaleLicenseProduct>  $products
     * @return array<int, array{purchased: int, available: int, sold: int, incomplete: int, blocked: int, reorder: bool, sold_out: bool}>
     */
    public function productStock(Organization $organization, Collection $products): array {
        $batches = ResaleLicenseBatch::query()->whereIn('product_id', $products->pluck('id')->all())->get(['id', 'product_id']);
        $counts = $this->batchCounts($organization, $batches->modelKeys());
        $empty = ['purchased' => 0, 'available' => 0, 'sold' => 0, 'incomplete' => 0, 'blocked' => 0];

        $stock = [];
        foreach ($products as $product) {
            $total = $empty;
            foreach ($batches->where('product_id', $product->id) as $batch) {
                foreach ($counts->get($batch->id, $empty) as $key => $count) {
                    $total[$key] += $count;
                }
            }
            $stock[$product->id] = $total + [
                'reorder' => $product->reorder_level !== null && $total['available'] <= $product->reorder_level,
                'sold_out' => $total['available'] === 0,
            ];
        }

        return $stock;
    }

    /** Geschlüsselter Abdruck eines Schlüssels (HMAC, nie ein ungeschützter Klartext-Hash). */
    public static function fingerprint(string $value): string {
        return BlindIndex::of('resale-license-key:' . trim($value));
    }

    /**
     * @return array{rows: list<array{line: int, position: int, added: int, complete: bool}>, errors: list<array{line: int, field: string, message: string}>, writes: list<array{0: int, 1: string, 2: string, 3: string}>}
     */
    private function analyzeImport(ResaleLicenseBatch $batch, string $csv): array {
        $lines = preg_split('/\r\n|\n|\r/', ltrim($csv, "\u{FEFF}")) ?: [];
        $rows = [];
        $errors = [];
        $writes = [];
        $error = static function (int $line, string $field, string $key, array $replace = []) use (&$errors): void {
            $errors[] = ['line' => $line, 'field' => $field, 'message' => (string) __('resale.license.import.error.' . $key, $replace)];
        };

        $headerLine = (string) ($lines[0] ?? '');
        $delimiter = CsvStringHelper::detectDelimiter($headerLine, [';', ',', "\t"]);
        $roles = $batch->keyRoles();
        $columns = [];
        foreach (CsvFacade::parseRows($headerLine, $delimiter)[0] ?? [] as $index => $cell) {
            $name = mb_strtolower(trim((string) $cell));
            if ($name === '') {
                continue;
            }
            $target = in_array($name, ['position', 'pos', 'nr'], true) ? 'position' : null;
            foreach ($roles as $role) {
                if ($name === $role['code'] || $name === mb_strtolower($role['label'])) {
                    $target = $role['code'];
                }
            }
            if ($target === null) {
                $error(1, (string) $cell, 'unknown_column', ['column' => Str::limit((string) $cell, 40)]);
            } elseif (in_array($target, $columns, true)) {
                $error(1, (string) $cell, 'duplicate_column', ['column' => Str::limit((string) $cell, 40)]);
            } else {
                $columns[$index] = $target;
            }
        }
        if (! in_array('position', $columns, true)) {
            $error(1, 'position', 'position_missing');
        }
        if ($errors !== []) {
            return ['rows' => [], 'errors' => $errors, 'writes' => []];
        }

        $units = $batch->units()->with('activeAssignment')->get()->keyBy('position');
        $existing = [];
        foreach (ResaleLicenseKey::query()->whereIn('unit_id', $units->modelKeys())->get(['unit_id', 'role', 'fingerprint']) as $key) {
            $existing[$key->unit_id][$key->role] = $key->fingerprint;
        }
        $elsewhere = [];
        foreach (ResaleLicenseKey::query()->where('product_id', $batch->product_id)->whereNotIn('unit_id', $units->modelKeys())->get(['role', 'fingerprint']) as $key) {
            $elsewhere[$key->role][$key->fingerprint] = true;
        }

        $seenPositions = [];
        $seenKeys = [];
        foreach (array_slice($lines, 1) as $offset => $text) {
            $line = $offset + 2;
            if (trim($text) === '') {
                continue;
            }
            $cells = CsvFacade::parseRows($text, $delimiter)[0] ?? [];
            $values = [];
            foreach ($columns as $index => $target) {
                $values[$target] = trim((string) ($cells[$index] ?? ''));
            }

            $position = $values['position'];
            if ($position === '' || ! ctype_digit($position)) {
                $error($line, 'position', 'invalid_position');

                continue;
            }
            $unit = $units->get((int) $position);
            if ($unit === null) {
                $error($line, 'position', 'unknown_position', ['position' => (int) $position]);

                continue;
            }
            if (isset($seenPositions[$unit->position])) {
                $error($line, 'position', 'duplicate_position', ['position' => $unit->position, 'first' => $seenPositions[$unit->position]]);

                continue;
            }
            $seenPositions[$unit->position] = $line;

            $added = 0;
            foreach ($roles as $role) {
                $value = $values[$role['code']] ?? '';
                if ($value === '') {
                    continue;
                }
                if (mb_strlen($value) > self::MAX_KEY_LENGTH) {
                    $error($line, $role['label'], 'key_too_long', ['max' => self::MAX_KEY_LENGTH]);

                    continue;
                }
                $fingerprint = self::fingerprint($value);
                if (isset($seenKeys[$role['code']][$fingerprint])) {
                    $error($line, $role['label'], 'duplicate_in_file', ['first' => $seenKeys[$role['code']][$fingerprint]]);

                    continue;
                }
                $seenKeys[$role['code']][$fingerprint] = $line;
                $current = $existing[$unit->id][$role['code']] ?? null;
                if ($current !== null) {
                    if (! hash_equals($current, $fingerprint)) {
                        $error($line, $role['label'], $unit->activeAssignment !== null ? 'sold' : 'conflict', ['position' => $unit->position]);
                    }

                    continue;
                }
                if ($unit->activeAssignment !== null) {
                    $error($line, $role['label'], 'sold', ['position' => $unit->position]);

                    continue;
                }
                if (isset($elsewhere[$role['code']][$fingerprint])) {
                    $error($line, $role['label'], 'duplicate_key');

                    continue;
                }
                $writes[] = [$unit->id, $role['code'], $value, $fingerprint];
                $added++;
            }

            $rows[] = [
                'line' => $line,
                'position' => $unit->position,
                'added' => $added,
                'complete' => count($existing[$unit->id] ?? []) + $added >= $batch->key_count,
            ];
        }

        return ['rows' => $rows, 'errors' => $errors, 'writes' => $writes];
    }

    private function lockUnit(ResaleLicenseUnit $unit): ResaleLicenseUnit {
        /** @var ResaleLicenseUnit $locked */
        $locked = ResaleLicenseUnit::query()->whereKey($unit->id)->lockForUpdate()->firstOrFail();
        $locked->loadCount('keys')->load(['batch', 'activeAssignment']);

        return $locked;
    }

    private function writeKey(ResaleLicenseUnit $unit, string $role, string $value, string $fingerprint, ?ResaleLicenseKey $current, User $actor): void {
        try {
            if ($current !== null) {
                $current->forceFill(['value' => $value, 'fingerprint' => $fingerprint, 'updated_by' => $actor->id])->save();

                return;
            }
            ResaleLicenseKey::query()->create([
                'organization_id' => $unit->organization_id,
                'unit_id' => $unit->id,
                'product_id' => $unit->batch->product_id,
                'role' => $role,
                'value' => $value,
                'fingerprint' => $fingerprint,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new LicenseStockException('duplicate_key', 'license_keys.' . $role);
        }
    }

    private function assertUniqueKey(ResaleLicenseUnit $unit, string $role, string $fingerprint, string $field): void {
        $taken = ResaleLicenseKey::query()
            ->where('product_id', $unit->batch->product_id)
            ->where('role', $role)
            ->where('fingerprint', $fingerprint)
            ->where('unit_id', '!=', $unit->id)
            ->exists();
        if ($taken) {
            throw new LicenseStockException('duplicate_key', $field);
        }
    }

    private function assertHolder(ResaleLicenseUnit $unit, Customer $customer, ?ForeignCustomer $foreign): void {
        if ((int) $customer->organization_id !== (int) $unit->organization_id) {
            throw new LicenseStockException('customer_foreign', 'customer_id');
        }
        if ($foreign !== null && (int) $foreign->customer_id !== (int) $customer->id) {
            throw new LicenseStockException('holder_mismatch', 'foreign_customer_id');
        }
    }

    private function assertReason(string $reason): void {
        if (trim($reason) === '') {
            throw new LicenseStockException('reason_required', 'reason');
        }
    }

    private function byToken(ResaleLicenseUnit $unit, ?string $token): ?ResaleLicenseAssignment {
        if ($token === null || $token === '') {
            return null;
        }

        return ResaleLicenseAssignment::query()
            ->where('organization_id', $unit->organization_id)
            ->where('request_token', $token)
            ->first();
    }

    private function assign(ResaleLicenseUnit $unit, Customer $customer, ?ForeignCustomer $foreign, CarbonImmutable $soldOn, ?string $invoiceReference, ?string $token, User $actor): ResaleLicenseAssignment {
        $assignment = ResaleLicenseAssignment::query()->create([
            'organization_id' => $unit->organization_id,
            'unit_id' => $unit->id,
            'active_unit_id' => $unit->id,
            'customer_id' => $customer->id,
            'foreign_customer_id' => $foreign?->id,
            'sold_on' => $soldOn->toDateString(),
            'invoice_reference' => $invoiceReference !== null && trim($invoiceReference) !== '' ? trim($invoiceReference) : null,
            'request_token' => $token !== null && $token !== '' ? $token : null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $unit->setRelation('activeAssignment', $assignment);

        return $assignment;
    }

    private function end(ResaleLicenseAssignment $assignment, LicenseAssignmentEnd $kind, string $reason, User $actor): void {
        $assignment->forceFill([
            'active_unit_id' => null,
            'ended_at' => CarbonImmutable::now(),
            'end_kind' => $kind,
            'end_reason' => Str::limit($reason, 252),
            'ended_by_user_id' => $actor->id,
            'updated_by' => $actor->id,
        ])->save();
    }

    private function importCacheKey(ResaleLicenseBatch $batch, User $actor, string $token): string {
        return 'resale-license-import:' . $batch->organization_id . ':' . $actor->id . ':' . $batch->id . ':' . $token;
    }
}
