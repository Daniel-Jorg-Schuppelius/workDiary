<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_11_120300_create_plugin_settings_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plugin-Einstellungen pro Organisation. Ersetzt die ENV-/config-only
 * Verwaltung: Admins aktivieren ein Plugin in der UI und pflegen API-Keys
 * direkt in der App. Das Feld `settings` ist verschlüsselt (s. PluginSetting-Cast).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('plugin_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('plugin_id', 64);
            $table->boolean('enabled')->default(false);
            $table->text('settings')->nullable(); // encrypted JSON-Blob
            $table->timestamps();

            $table->unique(['organization_id', 'plugin_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('plugin_settings');
    }
};
