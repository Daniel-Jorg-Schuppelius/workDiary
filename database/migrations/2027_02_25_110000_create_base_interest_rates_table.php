<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_25_110000_create_base_interest_rates_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-879: Basiszinssatz nach § 247 BGB (Bundesbank-Reihe) als Referenztabelle. */
return new class extends Migration {
    public function up(): void {
        Schema::create('base_interest_rates', function (Blueprint $table): void {
            $table->id();
            $table->date('valid_from')->unique();
            $table->decimal('rate', 6, 2);
            $table->string('source', 32);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('base_interest_rates');
    }
};
