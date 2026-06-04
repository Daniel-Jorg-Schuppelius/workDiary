<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_04_170000_create_minimum_wages_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gesetzlicher Mindestlohn je Organisation mit Gültig-ab-Historie. Künftige
 * Anhebungen können vorab eingetragen werden; der für ein Datum gültige Satz
 * ist der jüngste Eintrag mit `valid_from <= Datum`.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('minimum_wages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('valid_from');
            $table->decimal('hourly_amount', 6, 2);
            $table->string('note', 191)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'valid_from']);
            $table->index(['organization_id', 'valid_from']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('minimum_wages');
    }
};
