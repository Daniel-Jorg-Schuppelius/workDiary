<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_03_100000_create_recipe_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partyservice-Rezepturen (MVP-455): additiver Aufsatz auf den
 * branchenneutralen Rezeptkern aus MVP-061 (ProcedureTemplateVersion +
 * ProcedureMaterialRequirement). `recipe_profiles` trägt Grundausbeute/
 * Portionen und Allergen-Abweichungen je Rezeptversion; `recipe_menus`/
 * `recipe_menu_items` bilden Menü-/Buffetzusammenstellungen ab, die
 * ausschließlich auf veröffentlichte Rezeptstände verweisen (keine
 * Positionsduplikate).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('recipe_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'recprof_org_fk')->cascadeOnDelete();
            $table->foreignId('procedure_template_version_id')->constrained('procedure_template_versions', indexName: 'recprof_version_fk')->cascadeOnDelete();
            // Grundausbeute: X Portionen, optional als Ausgabemenge (z. B. 10 l).
            $table->decimal('base_portions', 10, 2)->default(1);
            $table->decimal('base_yield_qty', 12, 3)->nullable();
            $table->string('yield_unit', 20)->nullable();
            // Manuelle Allergen-Abweichungen: {added: [code], removed: [code], reason: string}
            $table->json('allergen_overrides')->nullable();
            $table->timestamps();

            $table->unique('procedure_template_version_id', 'recprof_version_unique');
            $table->index('organization_id', 'recprof_org_idx');
        });

        Schema::create('recipe_menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'recmenu_org_fk')->cascadeOnDelete();
            $table->string('name', 160);
            $table->date('event_date')->nullable();
            $table->unsignedInteger('guest_count')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'recmenu_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'event_date'], 'recmenu_org_date_idx');
        });

        Schema::create('recipe_menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'recmi_org_fk')->cascadeOnDelete();
            $table->foreignId('recipe_menu_id')->constrained('recipe_menus', indexName: 'recmi_menu_fk')->cascadeOnDelete();
            $table->foreignId('procedure_template_id')->constrained('procedure_templates', indexName: 'recmi_template_fk')->cascadeOnDelete();
            $table->decimal('portions_per_guest', 6, 2)->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['recipe_menu_id', 'procedure_template_id'], 'recmi_menu_template_unique');
            $table->index('organization_id', 'recmi_org_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('recipe_menu_items');
        Schema::dropIfExists('recipe_menus');
        Schema::dropIfExists('recipe_profiles');
    }
};
