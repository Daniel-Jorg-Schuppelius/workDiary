<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_130300_add_is_template_to_customer_circulars_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rundschreiben als Vorlage (MVP-1285): Vorlagen werden nie versendet, nur kopiert. */
return new class extends Migration {
    public function up(): void {
        Schema::table('customer_circulars', function (Blueprint $table): void {
            $table->boolean('is_template')->default(false)->after('portal_notice');
        });
    }

    public function down(): void {
        Schema::table('customer_circulars', function (Blueprint $table): void {
            $table->dropColumn('is_template');
        });
    }
};
