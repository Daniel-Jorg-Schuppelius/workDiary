<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_240100_route_calendly_unmatched_bookings_to_customers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Calendly-Buchungen ohne Kundentreffer (MVP-1610): Die offenen Einträge
 * trugen eine Gruppe ohne Gruppenbuchung und blieben im Integrations-Eingang
 * unsichtbar. Sie werden zu Einzelfällen mit Ziel „Kunde“; Name und E-Mail
 * aus der Buchung dienen dem Anlegen.
 */
return new class extends Migration {
    public function up(): void {
        DB::table('integration_inbox_items')
            ->where('plugin_id', 'calendly')
            ->where('external_type', 'calendly_invitee')
            ->where('status', 'open')
            ->orderBy('id')
            ->each(function (object $item): void {
                $snapshot = json_decode((string) ($item->remote_snapshot ?? ''), true);
                $snapshot = is_array($snapshot) ? $snapshot : [];
                $mapped = array_filter([
                    'name' => is_string($snapshot['name'] ?? null) ? $snapshot['name'] : null,
                    'email' => is_string($snapshot['email'] ?? null) ? $snapshot['email'] : null,
                ]);

                DB::table('integration_inbox_items')->where('id', $item->id)->update([
                    'group_key' => null,
                    'target_type' => 'customers',
                    'mapped_snapshot' => $mapped === [] ? null : json_encode($mapped, JSON_UNESCAPED_UNICODE),
                ]);
            });
    }

    public function down(): void {
        // Die frühere Gruppierung war ein Fehler; es gibt keinen Zustand, zu dem zurückzukehren lohnt.
    }
};
