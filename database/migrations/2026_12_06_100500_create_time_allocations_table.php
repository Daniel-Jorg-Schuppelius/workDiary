<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_100500_create_time_allocations_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-514 P1 (Feature 103): Zeitaufteilung — ein Zeiteintrag kann auf
 * mehrere fachliche Dimensionen (Projekt, Kostenstelle, Standort, …)
 * verteilt werden. Polymorph gegen BESTEHENDE Modelle (FQCN, kein
 * morphMap im Projekt); die Summe der Anteile wird gegen die Dauer des
 * Eintrags validiert (TimeAllocationService).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('time_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('time_entry_id')->constrained('time_entries')->cascadeOnDelete();
            $table->string('allocatable_type');
            $table->unsignedBigInteger('allocatable_id');
            $table->unsignedInteger('duration_minutes');
            $table->decimal('quantity', 10, 2)->nullable();
            $table->string('comment', 255)->nullable();
            $table->timestamps();

            $table->index(['allocatable_type', 'allocatable_id'], 'ta_allocatable_idx');
            $table->index(['organization_id', 'time_entry_id'], 'ta_org_entry_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('time_allocations');
    }
};
