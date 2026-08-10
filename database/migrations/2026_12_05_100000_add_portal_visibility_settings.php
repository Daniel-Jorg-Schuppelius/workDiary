<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_05_100000_add_portal_visibility_settings.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Portal-Sichtbarkeiten (MVP-511): kundenspezifische Freigaben der
 * Portalbereiche (customers.portal_settings) und kundensichere
 * Veröffentlichung einzelner Zeiten (time_entries.customer_visible_at).
 *
 * Kompatibilitätsstand: Kunden, die BEREITS aktive Portalkonten haben, sahen
 * vor MVP-511 alle Bereiche und sämtliche Projektzeiten allein durch den
 * Login. Diese Kunden bekommen einen EXPLIZITEN Stand, der das bisherige
 * Sichtfeld nachvollziehbar abbildet (alle Bereiche + Zeiten inkl.
 * Beschreibung + Kompatibilitäts-Scope „alle kundenbezogenen Zeiten").
 * Neue Kunden und neue Capabilities starten deny (portal_settings NULL).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->json('portal_settings')->nullable()->after('travel_settings');
        });
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->timestamp('customer_visible_at')->nullable()->after('exported');
        });

        $legacy = json_encode([
            'enabled' => true,
            'capabilities' => [
                'diary', 'time_entries', 'invoices', 'documents', 'assets',
                'open_issues', 'tickets', 'claims', 'rentals',
            ],
            'time_detail' => 'entries_with_description',
            'time_scope' => 'all',
            'migrated_legacy_at' => now()->toIso8601String(),
        ]);

        DB::table('customers')
            ->whereNull('portal_settings')
            ->whereIn('id', DB::table('users')->whereNotNull('customer_id')->select('customer_id'))
            ->update(['portal_settings' => $legacy]);
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('portal_settings');
        });
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropColumn('customer_visible_at');
        });
    }
};
