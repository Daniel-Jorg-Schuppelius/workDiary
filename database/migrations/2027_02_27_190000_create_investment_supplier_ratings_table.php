<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_190000_create_investment_supplier_ratings_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-928: Bewertung der Lieferanten je Investition (Termin, Kosten, Qualität). */
return new class extends Migration {
    public function up(): void {
        Schema::create('investment_supplier_ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'inv_sup_ratings_org_fk')->cascadeOnDelete();
            $table->foreignId('investment_case_id')->constrained('investment_cases', indexName: 'inv_sup_ratings_case_fk')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers', indexName: 'inv_sup_ratings_supplier_fk')->cascadeOnDelete();
            $table->unsignedTinyInteger('schedule_score');
            $table->unsignedTinyInteger('cost_score');
            $table->unsignedTinyInteger('quality_score');
            $table->text('note')->nullable();
            $table->foreignId('rated_by')->nullable()->constrained('users', indexName: 'inv_sup_ratings_rated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['investment_case_id', 'supplier_id'], 'inv_sup_ratings_case_supplier_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('investment_supplier_ratings');
    }
};
