<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_01_120100_add_asset_id_to_remote_pending_sessions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verknüpft eine offene Pending-Session optional mit einem bereits bekannten
 * Gerät. asset_id IS NULL bedeutet wie bisher "unbekannte Geräte-ID"; ist asset_id
 * gesetzt, gehört die Sitzung zu einem als Mehrkundengerät markierten Asset und
 * wartet auf die Zuordnung zu einem konkreten Kunden.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasTable('remote_pending_sessions') || Schema::hasColumn('remote_pending_sessions', 'asset_id')) {
            return;
        }
        Schema::table('remote_pending_sessions', function (Blueprint $table): void {
            $table->foreignId('asset_id')->nullable()->after('organization_id')
                ->constrained('assets')->nullOnDelete();
            $table->index(['organization_id', 'status', 'asset_id'], 'rps_asset_idx');
        });
    }

    public function down(): void {
        if (! Schema::hasTable('remote_pending_sessions') || ! Schema::hasColumn('remote_pending_sessions', 'asset_id')) {
            return;
        }
        Schema::table('remote_pending_sessions', function (Blueprint $table): void {
            $table->dropIndex('rps_asset_idx');
            $table->dropConstrainedForeignId('asset_id');
        });
    }
};
