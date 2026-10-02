<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_04_100000_create_takeoffs_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1058: Aufmaßblatt je Auftrag, Projekt oder LV mit Zeilen nach den
 * Formeln der REB-VB 23.003; gerechnet wird über das erechnung-toolkit.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('takeoffs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diary_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bill_of_quantity_id')->nullable()->constrained('bill_of_quantities')->nullOnDelete();
            $table->string('title', 200);
            $table->date('measured_on')->nullable();
            $table->string('status', 16)->default('draft');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('takeoff_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('takeoff_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('label', 120)->nullable();
            $table->string('description', 255)->nullable();
            $table->string('formula', 2);
            $table->json('values');
            $table->decimal('factor', 10, 3)->default(1);
            $table->decimal('quantity', 14, 4)->nullable();
            $table->string('unit', 16)->nullable();
            $table->foreignId('boq_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['takeoff_id', 'position']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('takeoff_lines');
        Schema::dropIfExists('takeoffs');
    }
};
