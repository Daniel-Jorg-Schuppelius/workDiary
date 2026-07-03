<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_01_120100_create_month_closure_events_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-016 — Append-only Audit pro Statuswechsel einer Monatsfreigabe.
 * Siehe ../WorkDiary-Architecture/monatsfreigabe.md §2.2 + §5.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('month_closure_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('month_closure_id')
                ->constrained('month_closures')->cascadeOnDelete();
            $table->string('event', 40);
            $table->foreignId('actor_user_id')
                ->constrained('users')->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['month_closure_id', 'created_at'], 'month_closure_events_chrono_idx');
            $table->index('event');
        });
    }

    public function down(): void {
        Schema::dropIfExists('month_closure_events');
    }
};
