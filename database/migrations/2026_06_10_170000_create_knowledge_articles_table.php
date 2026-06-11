<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_10_170000_create_knowledge_articles_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('knowledge_articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('title', 180);
            $table->string('slug', 200);
            // Problembeschreibung + Lösungsschritte (Markdown light, MVP: Klartext).
            $table->text('problem');
            $table->text('solution');
            $table->string('category', 80)->nullable();
            $table->string('status', 16)->default('draft');
            // MVP: nur `internal` (ganze Org). `team` ist im Enum vorgesehen,
            // aber noch nicht anknüpfbar (Teams sind m:n) — siehe Feature 011.
            $table->string('visibility', 16)->default('internal');
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('published_at')->nullable();
            // Denormalisierte Feedback-Zähler (Quelle: knowledge_article_feedback).
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('not_helpful_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'slug'], 'knowledge_org_slug_uq');
            $table->index(['organization_id', 'status'], 'knowledge_org_status_idx');
            $table->index(['organization_id', 'category'], 'knowledge_org_category_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('knowledge_articles');
    }
};
