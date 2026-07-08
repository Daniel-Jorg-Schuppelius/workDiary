<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_05_100000_create_idea_node_links_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Benannte Querverbindungen zwischen Knoten (Feature 054, MVP-137): die
 * additive, nicht-hierarchische Kantenart neben der primären Elternbeziehung
 * (die Baumstruktur bleibt für Navigation/Export/Verschieben eindeutig). Ein
 * gerichteter Link je (Quelle, Ziel); Selbstverweis per Service-Guard
 * ausgeschlossen. Kurze, explizite Index-/FK-Namen (64-Zeichen-MySQL-Limit).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('idea_node_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'idealink_org_fk')->cascadeOnDelete();
            $table->foreignId('idea_map_id')->constrained('idea_maps', indexName: 'idealink_map_fk')->cascadeOnDelete();
            $table->foreignId('source_node_id')->constrained('idea_nodes', indexName: 'idealink_src_fk')->cascadeOnDelete();
            $table->foreignId('target_node_id')->constrained('idea_nodes', indexName: 'idealink_tgt_fk')->cascadeOnDelete();
            $table->string('label', 120)->nullable();
            $table->string('color', 24)->default('default');
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'idealink_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_node_id', 'target_node_id'], 'idealink_pair_unique');
            $table->index('idea_map_id', 'idealink_map_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('idea_node_links');
    }
};
