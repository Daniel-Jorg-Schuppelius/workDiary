<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_05_120100_create_help_views_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('help_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('topic', 128);
            $table->string('locale', 8);
            // Anonyme Telemetrie: keine User-ID. was_helpful nullable für reine View-Tracks ohne Feedback.
            $table->boolean('was_helpful')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['topic', 'locale', 'created_at'], 'idx_help_views_topic_locale');
            $table->index(['organization_id', 'created_at'], 'idx_help_views_org_time');
        });
    }

    public function down(): void {
        Schema::dropIfExists('help_views');
    }
};
