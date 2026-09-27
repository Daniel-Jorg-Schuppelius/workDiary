<?php
/*
 * Created on   : Sat Sep 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_26_100000_create_investment_financing_variants_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-907: Finanzierungsvarianten (Kauf, Kredit, Leasing) je Investitionsoption. */
return new class extends Migration {
    public function up(): void {
        Schema::create('investment_financing_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investment_option_id')->constrained('investment_options', indexName: 'inv_fin_variants_option_fk')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->decimal('down_payment_amount', 12, 2)->default(0);
            $table->decimal('interest_rate', 6, 3)->nullable();
            $table->unsignedSmallInteger('term_months')->nullable();
            $table->decimal('rate_amount', 12, 2)->nullable();
            $table->decimal('residual_amount', 12, 2)->default(0);
            $table->decimal('fee_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('investment_financing_variants');
    }
};
