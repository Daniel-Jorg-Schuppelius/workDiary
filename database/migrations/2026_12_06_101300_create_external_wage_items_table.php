<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_101300_create_external_wage_items_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature-103-Delta (Q1 „Import von Bewegungsdaten"): externe
 * vergütungsrelevante Positionen (Essensgeld, Kilometer, Erschwernis-,
 * Akkordzulagen …) je Mitarbeiter/Tag mit Lohnartenbezug. Der
 * Zeitwirtschafts-Export nimmt sie als zusätzliche Zeilen mit.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('external_wage_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('item_date');
            $table->string('wage_type_code', 64);
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 16)->default('unit');
            $table->string('note', 255)->nullable();
            $table->string('source', 64)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'user_id', 'item_date'], 'ewi_org_user_date_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('external_wage_items');
    }
};
