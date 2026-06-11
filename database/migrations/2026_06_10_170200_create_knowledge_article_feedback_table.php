<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_10_170200_create_knowledge_article_feedback_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // „Hat geholfen / Hat nicht geholfen" — genau eine Wertung pro
        // User und Artikel (unique). Die Zähler auf knowledge_articles
        // sind denormalisiert und werden im Service nachgeführt.
        // Mandantengrenze transitiv über den Artikel (siehe Tenant-Audit).
        Schema::create('knowledge_article_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_article_id')->constrained('knowledge_articles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('helpful');
            $table->timestamp('created_at')->nullable();

            $table->unique(['knowledge_article_id', 'user_id'], 'knowledge_feedback_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('knowledge_article_feedback');
    }
};
