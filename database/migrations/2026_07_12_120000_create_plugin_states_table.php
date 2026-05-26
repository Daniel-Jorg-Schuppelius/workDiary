<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_12_120000_create_plugin_states_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Globaler State pro Plugin (NICHT pro Organisation, da Schema-Migrations
 * und Boot-Status tenant-übergreifend sind):
 *  - installed_version: zuletzt erfolgreich migriertes Plugin-Schema
 *  - last_health_*    : Ergebnis des letzten Healthchecks
 *  - failure_count    : Anzahl aufeinanderfolgender Boot-/Runtime-Fehler
 *  - disabled_reason  : globaler Kill-Switch (Auto-Disable nach N Fehlern)
 *
 * Pro-Org-Aktivierung bleibt in `plugin_settings.enabled`.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('plugin_states', function (Blueprint $table): void {
            $table->id();
            $table->string('plugin_id', 64)->unique();
            $table->string('installed_version', 32)->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->string('last_health_status', 16)->nullable();
            $table->text('last_health_message')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->text('disabled_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('plugin_states');
    }
};
