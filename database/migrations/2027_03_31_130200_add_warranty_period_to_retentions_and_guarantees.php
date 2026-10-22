<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_130200_add_warranty_period_to_retentions_and_guarantees.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gewährleistung und Sicherheit koppeln (MVP-1282): Ein Einbehalt bzw. eine
 * Bürgschaft kann auf die Gewährleistungsfrist zeigen, deren Ende sie freigibt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('invoice_retentions', function (Blueprint $table): void {
            $table->foreignId('warranty_period_id')->nullable()->after('due_on')->constrained('warranty_periods', indexName: 'inv_ret_warranty_fk')->nullOnDelete();
        });
        Schema::table('guarantees', function (Blueprint $table): void {
            $table->foreignId('warranty_period_id')->nullable()->after('invoice_retention_id')->constrained('warranty_periods', indexName: 'guarantee_warranty_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('guarantees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('warranty_period_id');
        });
        Schema::table('invoice_retentions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('warranty_period_id');
        });
    }
};
