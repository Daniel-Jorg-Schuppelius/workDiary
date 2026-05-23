<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_23_120100_create_open_issue_events_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('open_issue_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('open_issue_id')->constrained('open_issues')->cascadeOnDelete();
            $table->string('event', 40);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['open_issue_id', 'created_at'], 'open_issue_events_issue_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('open_issue_events');
    }
};
