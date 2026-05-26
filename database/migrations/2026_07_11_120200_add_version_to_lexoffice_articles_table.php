<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_11_120200_add_version_to_lexoffice_articles_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voraussetzung für bidirektionalen Sync von Lexoffice-Artikeln:
 *  - external_version: Lexoffice optimistic-locking-Marker (wird bei PUT mitgeschickt)
 *  - is_dirty: lokal verändert, wartet auf Push
 *  - last_pushed_at: letzter erfolgreicher Push-Zeitpunkt
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('lexoffice_articles', function (Blueprint $table): void {
            $table->unsignedInteger('external_version')->nullable()->after('external_id');
            $table->boolean('is_dirty')->default(false)->after('synced_at');
            $table->timestamp('last_pushed_at')->nullable()->after('is_dirty');
        });
    }

    public function down(): void {
        Schema::table('lexoffice_articles', function (Blueprint $table): void {
            $table->dropColumn(['external_version', 'is_dirty', 'last_pushed_at']);
        });
    }
};
