<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerMergeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\{DB, Schema};
use InvalidArgumentException;

/**
 * Führt zwei lokale Kunden-Datensätze zusammen (Dubletten-Bereinigung, z. B.
 * nach dem Toggl-Import). Alle abhängigen Datensätze werden vom Quell- auf den
 * Ziel-Kunden umgehängt, leere Ziel-Felder aus der Quelle aufgefüllt, der
 * Quell-Kunde anschließend hart gelöscht.
 *
 * Mandanten-/Sicherheits-Garantie: Quelle und Ziel müssen zur selben
 * Organisation gehören und dürfen nicht identisch sein.
 *
 * Kollisionsbehandlung:
 *  - projects (customer_id, slug) und sites (customer_id, code) tragen
 *    zusammengesetzte Unique-Indizes — kollidierende Quell-Slugs/-Codes werden
 *    vor dem Umhängen eindeutig gemacht.
 *  - external_references trägt einen Unique-Index über
 *    (plugin_id, external_type, referenceable_type, referenceable_id);
 *    kollidierende Quell-Referenzen werden verworfen (Ziel gewinnt).
 *  - taggables hat den Primärschlüssel (tag_id, taggable_id, taggable_type);
 *    Tags, die das Ziel bereits trägt, werden nicht doppelt umgehängt.
 */
class CustomerMergeService {
    /**
     * Tabellen mit direkter `customer_id`-Spalte. Werden bei Bedarf
     * (Schema-Check) per Bulk-UPDATE umgehängt. projects und sites werden
     * gesondert behandelt (zusammengesetzte Unique-Indizes).
     *
     * @var list<string>
     */
    private const CUSTOMER_ID_TABLES = [
        'invoices',
        'invoice_items',
        'foreign_customers',
        'assets',
        'rooms',
        'diary_entries',
        'service_tickets',
        'service_orders',
        'key_handovers',
        'travel_logs',
        'per_diem_trips',
        'sla_contracts',
        'recurrence_rules',
        'manufacturing_orders',
        'stock_deliveries',
        'stock_serials',
        'expenses',
        'events',
        'customer_queries',
        'billing_transfers',
        'lexoffice_vouchers',
        'material_usages',
        'privacy_incidents',
        'protocol_signature_tokens',
        'users',
    ];

    /**
     * Polymorphe Tabellen, deren Zeilen auf den Kunden zeigen können
     * (type-Spalte => id-Spalte). Werden umgehängt, wo der morph-Type auf
     * Customer zeigt. Keine eigenen Unique-Indizes → einfacher Bulk-UPDATE.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const MORPH_TABLES = [
        'contact_addresses' => ['addressable_type', 'addressable_id'],
        'contact_bank_accounts' => ['accountable_type', 'accountable_id'],
        'communication_notes' => ['notable_type', 'notable_id'],
        'attachments' => ['attachable_type', 'attachable_id'],
        'pending_external_conflicts' => ['referenceable_type', 'referenceable_id'],
    ];

    /**
     * Felder, die — sofern beim Ziel leer — aus der Quelle übernommen werden.
     *
     * @var list<string>
     */
    private const FILLABLE_FROM_SOURCE = [
        'company', 'vat_id', 'tax_number', 'lexoffice_contact_number',
        'contact_name', 'contact_persons', 'email', 'phone', 'mobile', 'fax',
        'homepage', 'address', 'address_street', 'address_zip', 'address_city',
        'country', 'timezone', 'color', 'hourly_rate', 'internal_rate', 'comment',
        'invoice_text', 'bank_account_holder', 'bank_iban', 'bank_bic', 'bank_name',
        'buyer_reference', 'debtor_no',
    ];

    /**
     * Hängt alle Daten von $source auf $target um und löscht $source.
     *
     * @param  array<string, mixed>  $fieldOverrides  Feldwerte, die unabhängig
     *         vom „leer"-Kriterium den Ziel-Wert setzen (UI-Feldauswahl).
     */
    public function merge(Customer $source, Customer $target, array $fieldOverrides = []): void {
        if ($source->getKey() === $target->getKey()) {
            throw new InvalidArgumentException('Quelle und Ziel dürfen nicht identisch sein.');
        }
        if ($source->organization_id !== $target->organization_id) {
            throw new InvalidArgumentException('Kunden gehören zu unterschiedlichen Organisationen.');
        }

        $morph = $source->getMorphClass();
        $sourceId = (int) $source->getKey();
        $targetId = (int) $target->getKey();

        DB::transaction(function () use ($source, $target, $sourceId, $targetId, $morph, $fieldOverrides): void {
            $this->repointProjects($sourceId, $targetId);
            $this->repointSites($sourceId, $targetId);
            $this->repointScalarTables($sourceId, $targetId);
            $this->repointExternalReferences($morph, $sourceId, $targetId);
            $this->repointAliases($morph, $sourceId, $targetId);
            $this->repointMorphTables($morph, $sourceId, $targetId);
            $this->repointTaggables($morph, $sourceId, $targetId);
            $this->mergeFields($source, $target, $fieldOverrides);

            // Hartes Löschen ohne DeletePolicy-Guard (Projekte/Refs bereits umgehängt). Quell-Kunde verschwindet
            // endgültig; der Audit-Log hält „deleted" fest.
            $source->delete();
        });
    }

    private function repointProjects(int $sourceId, int $targetId): void {
        if (! Schema::hasTable('projects') || ! Schema::hasColumn('projects', 'customer_id')) {
            return;
        }

        $hasSlug = Schema::hasColumn('projects', 'slug');
        $hasDefault = Schema::hasColumn('projects', 'is_default');

        // Slug-Kollisionen auflösen: Ziel-Slugs einsammeln, kollidierende Quell-Projekte vor dem Umhängen umbenennen.
        if ($hasSlug) {
            $targetSlugs = DB::table('projects')->where('customer_id', $targetId)->pluck('slug')->all();
            $taken = array_flip(array_map('strval', $targetSlugs));

            $sourceProjects = DB::table('projects')->where('customer_id', $sourceId)->get(['id', 'slug']);
            foreach ($sourceProjects as $row) {
                $slug = (string) $row->slug;
                if ($slug === '' || ! isset($taken[$slug])) {
                    $taken[$slug] = true;
                    continue;
                }
                $i = 2;
                do {
                    $candidate = $slug . '-' . $i++;
                } while (isset($taken[$candidate]));
                $taken[$candidate] = true;
                DB::table('projects')->where('id', $row->id)->update(['slug' => $candidate]);
            }
        }

        // Hat das Ziel bereits ein Standardprojekt, verlieren die Quell-Defaults ihren Default-Status (nur eines erlaubt).
        if ($hasDefault) {
            $targetHasDefault = DB::table('projects')
                ->where('customer_id', $targetId)->where('is_default', true)->exists();
            if ($targetHasDefault) {
                DB::table('projects')
                    ->where('customer_id', $sourceId)->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        }

        DB::table('projects')->where('customer_id', $sourceId)->update(['customer_id' => $targetId]);
    }

    private function repointSites(int $sourceId, int $targetId): void {
        if (! Schema::hasTable('sites') || ! Schema::hasColumn('sites', 'customer_id')) {
            return;
        }

        if (Schema::hasColumn('sites', 'code')) {
            $targetCodes = DB::table('sites')->where('customer_id', $targetId)->pluck('code')->all();
            $taken = array_flip(array_map('strval', $targetCodes));

            $sourceSites = DB::table('sites')->where('customer_id', $sourceId)->get(['id', 'code']);
            foreach ($sourceSites as $row) {
                $code = (string) $row->code;
                if ($code === '' || ! isset($taken[$code])) {
                    $taken[$code] = true;
                    continue;
                }
                $i = 2;
                do {
                    $candidate = $code . '-' . $i++;
                } while (isset($taken[$candidate]));
                $taken[$candidate] = true;
                DB::table('sites')->where('id', $row->id)->update(['code' => $candidate]);
            }
        }

        DB::table('sites')->where('customer_id', $sourceId)->update(['customer_id' => $targetId]);
    }

    private function repointScalarTables(int $sourceId, int $targetId): void {
        foreach (self::CUSTOMER_ID_TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'customer_id')) {
                continue;
            }
            DB::table($table)->where('customer_id', $sourceId)->update(['customer_id' => $targetId]);
        }
    }

    private function repointExternalReferences(string $morph, int $sourceId, int $targetId): void {
        if (! Schema::hasTable('external_references')) {
            return;
        }

        $sourceRefs = DB::table('external_references')
            ->where('referenceable_type', $morph)
            ->where('referenceable_id', $sourceId)
            ->get(['id', 'organization_id', 'plugin_id', 'external_type', 'external_id']);

        foreach ($sourceRefs as $ref) {
            $collision = DB::table('external_references')
                ->where('referenceable_type', $morph)
                ->where('referenceable_id', $targetId)
                ->where('plugin_id', $ref->plugin_id)
                ->where('external_type', $ref->external_type)
                ->exists();

            if ($collision) {
                // Ziel hat bereits eine Primär-Referenz für dieses Plugin/diesen Typ
                // (Unique-Index). Die abweichende Quell-Fremd-ID als Alias aufs Ziel
                // sichern, damit künftige Importe mit dem alten Schlüssel ohne
                // Inbox-Umweg direkt landen.
                $this->writeAlias($morph, $targetId, $ref);
                DB::table('external_references')->where('id', $ref->id)->delete();
                continue;
            }

            DB::table('external_references')->where('id', $ref->id)->update(['referenceable_id' => $targetId]);
        }
    }

    /**
     * Bestehende Aliase des Quell-Kunden (aus früheren Merges) auf das Ziel
     * umhängen, damit Alias-Ketten über mehrere Zusammenführungen gültig bleiben.
     * Kollidierende Quell-Aliase (gleiche Fremd-ID) werden verworfen (Ziel gewinnt).
     */
    private function repointAliases(string $morph, int $sourceId, int $targetId): void {
        if (! Schema::hasTable('external_reference_aliases')) {
            return;
        }

        $targetKeys = DB::table('external_reference_aliases')
            ->where('referenceable_type', $morph)
            ->where('referenceable_id', $targetId)
            ->get(['plugin_id', 'external_type', 'external_id'])
            ->map(fn($a): string => $a->plugin_id . '|' . $a->external_type . '|' . $a->external_id)
            ->all();

        $sourceAliases = DB::table('external_reference_aliases')
            ->where('referenceable_type', $morph)
            ->where('referenceable_id', $sourceId)
            ->get(['id', 'plugin_id', 'external_type', 'external_id']);

        foreach ($sourceAliases as $alias) {
            $key = $alias->plugin_id . '|' . $alias->external_type . '|' . $alias->external_id;
            if (in_array($key, $targetKeys, true)) {
                DB::table('external_reference_aliases')->where('id', $alias->id)->delete();
                continue;
            }
            DB::table('external_reference_aliases')->where('id', $alias->id)->update(['referenceable_id' => $targetId]);
        }
    }

    /**
     * Schreibt/aktualisiert einen Alias (Fremd-ID → Ziel). Idempotent über den
     * Unique-Schlüssel (organization_id, plugin_id, external_type, external_id).
     */
    private function writeAlias(string $morph, int $targetId, \stdClass $ref): void {
        if (! Schema::hasTable('external_reference_aliases')) {
            return;
        }

        $now = now();
        $exists = DB::table('external_reference_aliases')
            ->where('organization_id', $ref->organization_id)
            ->where('plugin_id', $ref->plugin_id)
            ->where('external_type', $ref->external_type)
            ->where('external_id', $ref->external_id)
            ->first(['id']);

        if ($exists !== null) {
            DB::table('external_reference_aliases')->where('id', $exists->id)->update([
                'referenceable_type' => $morph,
                'referenceable_id' => $targetId,
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('external_reference_aliases')->insert([
            'organization_id' => $ref->organization_id,
            'plugin_id' => $ref->plugin_id,
            'external_type' => $ref->external_type,
            'external_id' => $ref->external_id,
            'referenceable_type' => $morph,
            'referenceable_id' => $targetId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function repointMorphTables(string $morph, int $sourceId, int $targetId): void {
        foreach (self::MORPH_TABLES as $table => [$typeCol, $idCol]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $typeCol)) {
                continue;
            }
            DB::table($table)
                ->where($typeCol, $morph)
                ->where($idCol, $sourceId)
                ->update([$idCol => $targetId]);
        }
    }

    private function repointTaggables(string $morph, int $sourceId, int $targetId): void {
        if (! Schema::hasTable('taggables')) {
            return;
        }

        // Tags, die das Ziel bereits trägt, dürfen nicht doppelt umgehängt
        // werden (Primärschlüssel tag_id+taggable_id+taggable_type).
        $targetTagIds = DB::table('taggables')
            ->where('taggable_type', $morph)
            ->where('taggable_id', $targetId)
            ->pluck('tag_id')->all();

        if ($targetTagIds !== []) {
            DB::table('taggables')
                ->where('taggable_type', $morph)
                ->where('taggable_id', $sourceId)
                ->whereIn('tag_id', $targetTagIds)
                ->delete();
        }

        DB::table('taggables')
            ->where('taggable_type', $morph)
            ->where('taggable_id', $sourceId)
            ->update(['taggable_id' => $targetId]);
    }

    /**
     * Füllt leere Ziel-Felder aus der Quelle und wendet explizite Overrides an.
     *
     * @param  array<string, mixed>  $fieldOverrides
     */
    private function mergeFields(Customer $source, Customer $target, array $fieldOverrides): void {
        foreach (self::FILLABLE_FROM_SOURCE as $field) {
            $current = $target->getAttribute($field);
            $isEmpty = $current === null || $current === '' || $current === [];
            if ($isEmpty) {
                $sourceValue = $source->getAttribute($field);
                if ($sourceValue !== null && $sourceValue !== '' && $sourceValue !== []) {
                    $target->setAttribute($field, $sourceValue);
                }
            }
        }

        foreach ($fieldOverrides as $field => $value) {
            if (in_array($field, self::FILLABLE_FROM_SOURCE, true)) {
                $target->setAttribute($field, $value);
            }
        }

        if ($target->isDirty()) {
            $target->save();
        }
    }
}
