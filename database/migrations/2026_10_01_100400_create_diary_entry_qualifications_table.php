<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_100400_create_diary_entry_qualifications_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qualifikations-Anforderungen je Auftrag (Feature 028, Rang 53): bisher
 * existierten Anforderungen nur in der Dienstplan-Welt (shift_type_
 * qualifications/CoverageRequirement) — die Auftrags-Qualifikationsmatrix
 * braucht sie am DiaryEntry.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('diary_entry_qualifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diary_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('qualification_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['diary_entry_id', 'qualification_id'], 'deq_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('diary_entry_qualifications');
    }
};
