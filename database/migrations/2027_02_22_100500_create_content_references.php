<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_22_100500_create_content_references.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Verweise und Rückverweise (MVP-811, Feature 155): ein gemeinsames Modell
 * für Verweise zwischen Inhalten. Die einseitigen Tabellen
 * `knowledge_article_links` (Artikel → Auftrag/Asset/Kunde/Protokoll/Problem)
 * und `idea_node_references` (Ideenknoten → überführtes oder verknüpftes Ziel)
 * wandern hinein; ihre Fachbedeutung steckt in `kind`.
 *
 * Quelle und Ziel sind polymorph und tragen deshalb keinen Fremdschlüssel —
 * verwaiste Zeilen fallen beim Auflösen weg.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('content_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'cref_org_fk')->cascadeOnDelete();
            $table->string('source_type', 120);
            $table->unsignedBigInteger('source_id');
            $table->string('target_type', 120);
            $table->unsignedBigInteger('target_id');
            $table->string('kind', 24);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'cref_creator_fk')->nullOnDelete();
            $table->timestamps();
            $table->unique(['source_type', 'source_id', 'target_type', 'target_id', 'kind'], 'cref_unique');
            $table->index(['source_type', 'source_id'], 'cref_source_idx');
            $table->index(['target_type', 'target_id'], 'cref_target_idx');
        });

        if (Schema::hasTable('knowledge_article_links')) {
            DB::statement(
                'INSERT INTO content_references (organization_id, source_type, source_id, target_type, target_id, kind, created_by, created_at, updated_at) '
                . 'SELECT a.organization_id, ?, l.knowledge_article_id, l.linkable_type, l.linkable_id, ?, l.created_by_user_id, l.created_at, l.created_at '
                . 'FROM knowledge_article_links l INNER JOIN knowledge_articles a ON a.id = l.knowledge_article_id',
                ['App\Models\Knowledge\KnowledgeArticle', 'linked'],
            );
            Schema::drop('knowledge_article_links');
        }

        if (Schema::hasTable('idea_node_references')) {
            DB::statement(
                'INSERT INTO content_references (organization_id, source_type, source_id, target_type, target_id, kind, created_by, created_at, updated_at) '
                . 'SELECT organization_id, ?, idea_node_id, target_type, target_id, kind, created_by, created_at, updated_at FROM idea_node_references',
                ['App\Models\Ideas\IdeaNode'],
            );
            Schema::drop('idea_node_references');
        }
    }

    public function down(): void {
        Schema::create('knowledge_article_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_article_id')->constrained('knowledge_articles')->cascadeOnDelete();
            $table->string('linkable_type', 64);
            $table->unsignedBigInteger('linkable_id');
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['knowledge_article_id', 'linkable_type', 'linkable_id'], 'knowledge_link_uq');
            $table->index(['linkable_type', 'linkable_id'], 'knowledge_link_linkable_idx');
        });
        Schema::create('idea_node_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'idearef_org_fk')->cascadeOnDelete();
            $table->foreignId('idea_node_id')->constrained('idea_nodes', indexName: 'idearef_node_fk')->cascadeOnDelete();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->string('kind', 16);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'idearef_creator_fk')->nullOnDelete();
            $table->timestamps();
            $table->unique(['idea_node_id', 'target_type', 'kind'], 'idearef_conv_unique');
            $table->index(['target_type', 'target_id'], 'idearef_target_idx');
        });

        DB::statement(
            'INSERT INTO knowledge_article_links (knowledge_article_id, linkable_type, linkable_id, created_by_user_id, created_at) '
            . 'SELECT source_id, target_type, target_id, created_by, created_at FROM content_references WHERE source_type = ? AND kind = ? AND created_by IS NOT NULL',
            ['App\Models\Knowledge\KnowledgeArticle', 'linked'],
        );
        DB::statement(
            'INSERT INTO idea_node_references (organization_id, idea_node_id, target_type, target_id, kind, created_by, created_at, updated_at) '
            . 'SELECT organization_id, source_id, target_type, target_id, kind, created_by, created_at, updated_at FROM content_references WHERE source_type = ?',
            ['App\Models\Ideas\IdeaNode'],
        );

        Schema::dropIfExists('content_references');
    }
};
