<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_170000_create_safety_events_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sicherheitsereignis-Register (Feature 013): Unfall, Beinaheunfall,
 * Gefährdung oder Mangel mit Schweregrad, Sofortmaßnahme, Ursachenanalyse
 * und Statusmaschine. event_no läuft je Organisation; subject_* bindet
 * optional ein fachliches Subjekt (DiaryEntry, Asset, Room).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('safety_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedInteger('event_no');
            $table->string('kind', 20);
            $table->string('severity', 20)->default('low');
            $table->timestamp('occurred_at');
            $table->string('location', 180)->nullable();
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('reported_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('affected_person', 180)->nullable();
            $table->text('description');
            $table->text('immediate_action')->nullable();
            $table->string('status', 24)->default('reported');
            $table->text('root_cause')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'event_no'], 'safety_events_org_no_uq');
            $table->index(['organization_id', 'status', 'severity'], 'safety_events_org_status_idx');
            $table->index(['kind', 'occurred_at'], 'safety_events_kind_idx');
            $table->index(['subject_type', 'subject_id'], 'safety_events_subject_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('safety_events');
    }
};
