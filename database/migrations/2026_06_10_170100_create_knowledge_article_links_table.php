<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_10_170100_create_knowledge_article_links_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Verknüpfung „Artikel hat bei diesem Auftrag/Asset/Kunden/Protokoll
        // geholfen". Mandantengrenze transitiv über den tenant-gebundenen
        // Artikel (knowledge_articles.organization_id) — analog
        // CommunicationNoteParticipant, siehe Allow-List im Tenant-Audit.
        Schema::create('knowledge_article_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_article_id')->constrained('knowledge_articles')->cascadeOnDelete();
            // Polymorpher Bezug: DiaryEntry|Asset|Customer|Protocol (Allow-List im Controller/Service).
            $table->string('linkable_type', 64);
            $table->unsignedBigInteger('linkable_id');
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['knowledge_article_id', 'linkable_type', 'linkable_id'], 'knowledge_link_uq');
            $table->index(['linkable_type', 'linkable_id'], 'knowledge_link_linkable_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('knowledge_article_links');
    }
};
