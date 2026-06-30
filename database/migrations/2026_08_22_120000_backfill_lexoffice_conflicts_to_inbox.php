<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_22_120000_backfill_lexoffice_conflicts_to_inbox.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Übernimmt offene Lexoffice-Kontaktkonflikte aus pending_external_conflicts in
 * die universelle Zuordnungs-Inbox (MVP-103). Idempotent über den dedupe_key.
 * Die alten Zeilen bleiben unangetastet (die Tabelle wird auch von der
 * Inventory-Outbox genutzt); die alte Lexoffice-Konflikt-Route leitet nur noch
 * auf die neue Inbox um.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasTable('integration_inbox_items') || ! Schema::hasTable('pending_external_conflicts')) {
            return;
        }

        $conflicts = DB::table('pending_external_conflicts')
            ->where('plugin_id', 'lexoffice')
            ->where('status', 'open')
            ->get();

        foreach ($conflicts as $row) {
            $remote = $this->decode($row->remote_snapshot);
            $mapped = $this->mapLexoffice($remote);
            $dedupeKey = 'contact:' . $row->external_id . ':' . class_basename((string) $row->referenceable_type);

            $exists = DB::table('integration_inbox_items')
                ->where('organization_id', $row->organization_id)
                ->where('plugin_id', 'lexoffice')
                ->where('dedupe_key', $dedupeKey)
                ->exists();
            if ($exists) {
                continue;
            }

            DB::table('integration_inbox_items')->insert([
                'organization_id' => $row->organization_id,
                'plugin_id' => 'lexoffice',
                'source' => 'api',
                'target_type' => $row->referenceable_type,
                'external_type' => 'contact',
                'external_id' => $row->external_id,
                'dedupe_key' => $dedupeKey,
                'case_type' => 'conflict',
                'status' => 'open',
                'referenceable_type' => $row->referenceable_type,
                'referenceable_id' => $row->referenceable_id,
                'remote_snapshot' => json_encode($remote, JSON_UNESCAPED_UNICODE),
                'mapped_snapshot' => json_encode($mapped, JSON_UNESCAPED_UNICODE),
                'local_snapshot' => $row->local_snapshot,
                'diff_fields' => $row->diff_fields,
                'display_title' => (string) ($mapped['name'] ?? $mapped['company'] ?? $row->external_id),
                'display_subtitle' => ($mapped['email'] ?? $mapped['vat_id'] ?? null),
                'created_at' => $row->created_at ?? now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void {
        // Backfill ist nicht reversibel (Quelle bleibt erhalten).
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(mixed $json): array {
        if (is_array($json)) {
            return $json;
        }

        return (array) (json_decode((string) $json, true) ?: []);
    }

    /**
     * Minimaler Lexoffice-Roh-JSON → lokales Customer/Supplier-Schema (deckt die
     * skalaren Konfliktfelder ab; gespiegelt aus LexofficeContactSync).
     *
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    private function mapLexoffice(array $r): array {
        $isCompany = ! empty(data_get($r, 'company.name'));
        $name = $isCompany
            ? (string) data_get($r, 'company.name')
            : trim(((string) data_get($r, 'person.firstName', '')) . ' ' . ((string) data_get($r, 'person.lastName', '')));
        $vat = (string) data_get($r, 'company.vatRegistrationId', '') ?: (string) data_get($r, 'company.taxNumber', '');
        $mails = (array) data_get($r, 'emailAddresses.business', []);

        return array_filter([
            'name' => $name !== '' ? $name : null,
            'company' => $isCompany ? (string) data_get($r, 'company.name') : null,
            'vat_id' => $vat ?: null,
            'tax_number' => (string) data_get($r, 'company.taxNumber', '') ?: null,
            'email' => ((string) ($mails[0] ?? data_get($r, 'emailAddresses.private.0', ''))) ?: null,
            'phone' => (string) data_get($r, 'phoneNumbers.business.0', '') ?: null,
            'mobile' => (string) data_get($r, 'phoneNumbers.mobile.0', '') ?: null,
            'fax' => (string) data_get($r, 'phoneNumbers.fax.0', '') ?: null,
            'comment' => (string) data_get($r, 'note', '') ?: null,
            'address_street' => (string) data_get($r, 'addresses.billing.0.street', '') ?: null,
            'address_zip' => (string) data_get($r, 'addresses.billing.0.zip', '') ?: null,
            'address_city' => (string) data_get($r, 'addresses.billing.0.city', '') ?: null,
            'country' => (string) data_get($r, 'addresses.billing.0.countryCode', '') ?: null,
        ], static fn($v) => $v !== null);
    }
};
