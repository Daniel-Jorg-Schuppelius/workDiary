<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_370000_add_carbon_footprint_fields_to_articles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-960: Emissionsfaktor je Zukaufartikel und Prozessemissionen je Stück. */
return new class extends Migration {
    public function up(): void {
        Schema::table('articles', function (Blueprint $table): void {
            $table->decimal('pcf_factor_kg', 12, 4)->nullable();
            $table->decimal('pcf_process_kg', 12, 4)->nullable();
            $table->string('pcf_source', 200)->nullable();
        });
    }

    public function down(): void {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn(['pcf_factor_kg', 'pcf_process_kg', 'pcf_source']);
        });
    }
};
