<?php
/*
 * Created on   : Fri Aug 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_102100_add_einvoice_options_and_pdf_import_to_invoices.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('delivery_format', 24)->default('pdf')->after('number_source');
            $table->string('buyer_reference', 100)->nullable()->after('delivery_format');
            $table->json('import_metadata')->nullable()->after('buyer_reference');
        });
    }

    public function down(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['delivery_format', 'buyer_reference', 'import_metadata']);
        });
    }
};
