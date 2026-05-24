<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_05_120000_create_help_topics_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('help_topics', function (Blueprint $table): void {
            $table->id();
            $table->string('topic', 128);
            $table->string('locale', 8);
            $table->string('title', 255);
            $table->json('audience')->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->longText('body_md');
            $table->longText('body_html');
            $table->json('related')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['topic', 'locale'], 'uniq_help_topic_locale');
            $table->index('topic', 'idx_help_topic');
        });
    }

    public function down(): void {
        Schema::dropIfExists('help_topics');
    }
};
