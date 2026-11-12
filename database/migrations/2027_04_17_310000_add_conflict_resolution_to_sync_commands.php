<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_310000_add_conflict_resolution_to_sync_commands.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1595 (K2-05): Entscheidung über einen Offline-Konflikt — übernommen,
 * verworfen oder erneut gesendet. Offene Konflikte sind die ohne `resolved_at`.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('sync_commands', function (Blueprint $table): void {
            if (! Schema::hasColumn('sync_commands', 'resolution')) {
                $table->string('resolution', 16)->nullable()->after('result_errors');
                $table->timestamp('resolved_at')->nullable()->after('resolution');
                $table->uuid('resent_client_uuid')->nullable()->after('resolved_at');
                $table->index(['user_id', 'result_status', 'resolved_at'], 'sync_cmd_user_open_conflict_idx');
            }
        });
    }

    public function down(): void {
        Schema::table('sync_commands', function (Blueprint $table): void {
            $table->dropIndex('sync_cmd_user_open_conflict_idx');
            $table->dropColumn(['resolution', 'resolved_at', 'resent_client_uuid']);
        });
    }
};
