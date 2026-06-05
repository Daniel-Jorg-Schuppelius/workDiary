<?php
/*
 * Created on   : Thu Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_11_120100_add_organization_to_plugin_state.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plugin-Zustand + Fehlerprotokoll werden pro Organisation geführt. Per-Org-
 * Plugins (eigene API-Keys je Org) erhalten so je Organisation einen eigenen
 * Healthcheck-Status und Auto-Disable; globale Plugins nutzen weiterhin
 * organization_id = null. Bestehende (globale) Zeilen bleiben unverändert.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('plugin_states', function (Blueprint $table): void {
            $table->dropUnique(['plugin_id']);
            $table->foreignId('organization_id')->nullable()->after('plugin_id')->constrained()->nullOnDelete();
            $table->unique(['plugin_id', 'organization_id']);
        });

        Schema::table('plugin_errors', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('plugin_id')->constrained()->nullOnDelete();
            $table->index(['plugin_id', 'organization_id']);
        });
    }

    public function down(): void {
        Schema::table('plugin_errors', function (Blueprint $table): void {
            $table->dropIndex(['plugin_id', 'organization_id']);
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::table('plugin_states', function (Blueprint $table): void {
            $table->dropUnique(['plugin_id', 'organization_id']);
            $table->dropConstrainedForeignId('organization_id');
            $table->unique('plugin_id');
        });
    }
};
