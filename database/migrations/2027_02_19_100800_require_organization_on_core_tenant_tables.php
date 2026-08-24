<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Vollscan 2026-08-23, F6 (Entscheid E5): Kerntabellen bekommen
 * organization_id NOT NULL + RESTRICT — NULL-Zeilen sind durch den
 * OrganizationScope unsichtbar (unlöschbare Geister), und SET NULL beim
 * Org-Delete würde genau solche erzeugen. Der Purge löscht die Zeilen
 * ohnehin explizit vor der Organisation. Bricht ab, wenn NULL-Bestand
 * existiert (Betreiber muss zuordnen). App-seitig wirft
 * BelongsToOrganization::creating für diese Tabellen jetzt statt still
 * nichts zu füllen. Nur MySQL.
 */
return new class extends Migration {
    /** @var list<string> Muss mit BelongsToOrganization::ORG_REQUIRED_TABLES übereinstimmen. */
    private const TABLES = ['invoices', 'time_entries', 'customers', 'tasks', 'suppliers', 'articles', 'timesheets'];

    public function up(): void {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }
        foreach (self::TABLES as $table) {
            $orphans = DB::table($table)->whereNull('organization_id')->count();
            if ($orphans > 0) {
                throw new RuntimeException("{$table}: {$orphans} Zeilen ohne organization_id — vor dieser Migration zuordnen.");
            }
        }
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropForeign($table . '_organization_id_foreign');
            });
            DB::statement("ALTER TABLE {$table} MODIFY organization_id BIGINT UNSIGNED NOT NULL");
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->foreign('organization_id', $table . '_organization_id_foreign')->references('id')->on('organizations')->restrictOnDelete();
            });
        }
    }

    public function down(): void {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropForeign($table . '_organization_id_foreign');
            });
            DB::statement("ALTER TABLE {$table} MODIFY organization_id BIGINT UNSIGNED NULL");
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->foreign('organization_id', $table . '_organization_id_foreign')->references('id')->on('organizations')->nullOnDelete();
            });
        }
    }
};
