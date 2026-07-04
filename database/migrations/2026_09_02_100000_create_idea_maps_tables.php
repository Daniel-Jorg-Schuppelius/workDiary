<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_02_100000_create_idea_maps_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ideenlandkarten (Feature 054, MVP-104/105): Karten, hierarchische Knoten,
 * Freigaben und Knoten-Referenzen. `private` ist Default — Sichtbarkeit läuft
 * ausschließlich über Eigentümer + Freigaben (IdeaMapPolicy), nie über die
 * Org-Zugehörigkeit allein. Kurze, explizite Index-/FK-Namen
 * (64-Zeichen-MySQL-Limit). Wurzelknoten-Invariante (genau einer je Karte)
 * wird im Service erzwungen (kein partielles Unique in MySQL).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('idea_maps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'ideamap_org_fk')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users', indexName: 'ideamap_creator_fk')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users', indexName: 'ideamap_owner_fk')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('visibility', 16)->default('private'); // abgeleitet: shared solange aktive Freigaben existieren
            $table->foreignId('customer_id')->nullable()->constrained('customers', indexName: 'ideamap_cust_fk')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects', indexName: 'ideamap_proj_fk')->nullOnDelete();
            $table->foreignId('diary_entry_id')->nullable()->constrained('diary_entries', indexName: 'ideamap_diary_fk')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['organization_id', 'owner_user_id'], 'ideamap_org_owner_idx');
        });

        Schema::create('idea_nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'ideanode_org_fk')->cascadeOnDelete();
            $table->foreignId('idea_map_id')->constrained('idea_maps', indexName: 'ideanode_map_fk')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('idea_nodes', indexName: 'ideanode_parent_fk')->cascadeOnDelete();
            $table->boolean('is_root')->default(false);
            $table->string('title', 500);
            $table->text('note')->nullable();
            $table->string('color', 24)->default('default');
            $table->string('node_status', 24)->nullable();
            $table->integer('pos_x')->nullable();  // Canvas-Position (P3)
            $table->integer('pos_y')->nullable();
            $table->unsignedInteger('sort_order')->default(0); // Gliederungs-Reihenfolge unter dem Elternknoten
            $table->unsignedInteger('lock_version')->default(1); // optimistische Sperre (P4)
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'ideanode_creator_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'ideanode_updater_fk')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['idea_map_id', 'parent_id', 'sort_order'], 'ideanode_map_parent_idx');
        });

        Schema::create('idea_map_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'ideashare_org_fk')->cascadeOnDelete();
            $table->foreignId('idea_map_id')->constrained('idea_maps', indexName: 'ideashare_map_fk')->cascadeOnDelete();
            // Genau eines von beiden (Service-Guard): Personen- ODER Teamfreigabe.
            $table->foreignId('user_id')->nullable()->constrained('users', indexName: 'ideashare_user_fk')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams', indexName: 'ideashare_team_fk')->cascadeOnDelete();
            $table->string('role', 16); // editor / viewer
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'ideashare_creator_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['idea_map_id', 'user_id', 'team_id'], 'ideashare_unique');
        });

        Schema::create('idea_node_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'idearef_org_fk')->cascadeOnDelete();
            $table->foreignId('idea_node_id')->constrained('idea_nodes', indexName: 'idearef_node_fk')->cascadeOnDelete();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->string('kind', 16); // converted / linked
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'idearef_creator_fk')->nullOnDelete();
            $table->timestamps();

            // Idempotenz der Überführung: je Knoten höchstens ein Ziel je Typ und Art.
            $table->unique(['idea_node_id', 'target_type', 'kind'], 'idearef_conv_unique');
            $table->index(['target_type', 'target_id'], 'idearef_target_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('idea_node_references');
        Schema::dropIfExists('idea_map_shares');
        Schema::dropIfExists('idea_nodes');
        Schema::dropIfExists('idea_maps');
    }
};
