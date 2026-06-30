<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_23_120000_add_group_key_to_integration_inbox_items.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gruppen-Schlüssel für die universelle Zuordnungs-Inbox. Zeit-Import-Einträge
 * (Toggl/OpenProject/RemoteSupport) werden je (client, project) gruppiert und
 * als Gruppe einem Kunden + Projekt zugeordnet und gebucht (MVP-103, Phase 2b).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('integration_inbox_items', function (Blueprint $table): void {
            $table->string('group_key', 191)->nullable()->after('dedupe_key');
            $table->index(['organization_id', 'plugin_id', 'group_key', 'status'], 'iii_group_idx');
        });
    }

    public function down(): void {
        Schema::table('integration_inbox_items', function (Blueprint $table): void {
            $table->dropIndex('iii_group_idx');
            $table->dropColumn('group_key');
        });
    }
};
