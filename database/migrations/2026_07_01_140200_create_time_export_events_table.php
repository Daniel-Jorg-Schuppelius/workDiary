<?php
/*
 * Created on   : Wed Jul 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_01_140200_create_time_export_events_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only Audit-Trail für TimeExport (MVP-019).
 *
 * Events: export.preparing | export.ready | export.downloaded |
 *         export.delivered | export.rejected | export.superseded |
 *         export.deleted
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('time_export_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('time_export_id')->constrained()->cascadeOnDelete();
            $t->string('event', 32);
            $t->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('note')->nullable();
            $t->json('payload')->nullable();
            $t->timestamp('created_at')->useCurrent();

            $t->index(['time_export_id', 'event'], 'tee_export_event_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('time_export_events');
    }
};
