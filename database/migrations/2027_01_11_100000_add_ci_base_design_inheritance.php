<?php
/*
 * Created on   : Mon Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_11_100000_add_ci_base_design_inheritance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #83 (CI-Basisdesign): Design-Varianten können für eine ganze
 * Dokumentfamilie gelten und vom org-weiten Basisdesign (is_default-Profil)
 * erben — Versionen speichern dann nur die ausdrücklich überschriebenen
 * Sektionen. NULL = eigenständiges Profil (Bestandsverhalten unverändert).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('document_render_profiles', function (Blueprint $table): void {
            // Familien-Variante (sales/procurement/evidence/special); die
            // spezifischere Dokumentart-Bindung (document_kinds) gewinnt.
            $table->string('document_family', 20)->nullable()->after('document_kinds');
        });

        Schema::table('document_render_profile_versions', function (Blueprint $table): void {
            // Erbt vom Basisdesign: Liste der überschriebenen Sektionen
            // (layout, assets, block_rules, table_style). NULL = eigenständig.
            $table->json('override_sections')->nullable()->after('table_style');
        });
    }

    public function down(): void {
        Schema::table('document_render_profiles', function (Blueprint $table): void {
            $table->dropColumn('document_family');
        });
        Schema::table('document_render_profile_versions', function (Blueprint $table): void {
            $table->dropColumn('override_sections');
        });
    }
};
