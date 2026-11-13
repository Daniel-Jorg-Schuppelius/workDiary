<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_18_300000_create_carry_forward_notes_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merker für die nächste Periode (Feature 201, MVP-1645): ein Hinweis gehört
 * zur Reihe (`series_key`, vom Träger), nicht zum einzelnen Vorgang. Er gilt
 * ab der Zielperiode (`period_key`, Beginn in `valid_from` für die Abfrage)
 * und bleibt offen, bis ihn jemand an einem Vorkommen abhakt
 * (`resolved_*`, Vermerk in `resolution_note`). Herkunft und Erledigung
 * verweisen polymorph auf das Vorkommen (Morph-Alias), ohne Fremdschlüssel:
 * der Merker überlebt das Löschen eines einzelnen Vorgangs.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('carry_forward_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('series_key', 191);
            $table->string('period_key', 10);
            $table->date('valid_from');
            $table->text('note');
            $table->string('origin_type', 64)->nullable();
            $table->unsignedBigInteger('origin_id')->nullable();
            $table->string('origin_period_key', 10)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolved_occurrence_type', 64)->nullable();
            $table->unsignedBigInteger('resolved_occurrence_id')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'series_key', 'resolved_at', 'valid_from'], 'cfn_series_idx');
            $table->index(['origin_type', 'origin_id'], 'cfn_origin_idx');
            $table->index(['resolved_occurrence_type', 'resolved_occurrence_id'], 'cfn_resolved_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('carry_forward_notes');
    }
};
