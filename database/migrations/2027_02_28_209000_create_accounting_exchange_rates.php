<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_209000_create_accounting_exchange_rates.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-1012: Monatskurse je Währung für die Umrechnung von Fremdwährungsbelegen. */
return new class extends Migration {
    public function up(): void {
        Schema::create('accounting_exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->date('period');
            $table->decimal('rate', 18, 6);
            $table->string('source', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'currency', 'period'], 'acc_fx_org_currency_period_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('accounting_exchange_rates');
    }
};
