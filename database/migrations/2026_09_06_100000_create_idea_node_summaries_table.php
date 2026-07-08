<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_06_100000_create_idea_node_summaries_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Boundaries / Zusammenfassungen (Feature 054, MVP-137): eine benannte Klammer
 * über einen zusammenhängenden Bereich der Kinder eines Knotens
 * (`start`..`end`-Index). Wie in Mind Elixir modelliert; additiv neben der
 * Baumstruktur. Kurze, explizite Index-/FK-Namen (64-Zeichen-MySQL-Limit).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('idea_node_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'ideasum_org_fk')->cascadeOnDelete();
            $table->foreignId('idea_map_id')->constrained('idea_maps', indexName: 'ideasum_map_fk')->cascadeOnDelete();
            $table->foreignId('parent_node_id')->constrained('idea_nodes', indexName: 'ideasum_parent_fk')->cascadeOnDelete();
            $table->unsignedInteger('start_index');
            $table->unsignedInteger('end_index');
            $table->string('label', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'ideasum_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->index('idea_map_id', 'ideasum_map_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('idea_node_summaries');
    }
};
