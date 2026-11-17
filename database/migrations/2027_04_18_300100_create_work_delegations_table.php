<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_18_300100_create_work_delegations_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vertretung bei Abwesenheit (Feature 202, MVP-1647): wer wen von wann bis wann
 * vertritt. Ohne `valid_until` gilt die Vertretung bis „Vertretung beenden“
 * (`ended_at`/`ended_by`). Anlass (`kind`) und die auslösende Abwesenheit
 * (`absence_type`/`absence_id`, Morph-Alias von Urlaub oder Krankmeldung) sind
 * optional; eine freie Vertretung hat keine. Die Vertretung ändert keine
 * Datensätze und verleiht keine Rechte — sie wird beim Lesen ausgewertet
 * ({@see \App\Services\Delegation\ActsForResolver}).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('work_delegations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('represented_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('deputy_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->string('kind', 16);
            $table->string('absence_type', 64)->nullable();
            $table->unsignedBigInteger('absence_id')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'represented_user_id', 'valid_from'], 'wd_represented_idx');
            $table->index(['organization_id', 'deputy_user_id', 'valid_from'], 'wd_deputy_idx');
            $table->index(['absence_type', 'absence_id'], 'wd_absence_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('work_delegations');
    }
};
