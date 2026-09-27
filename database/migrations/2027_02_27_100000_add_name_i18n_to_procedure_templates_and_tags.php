<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_100000_add_name_i18n_to_procedure_templates_and_tags.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-912: übersetzte Namen für Prozedurvorlagen und Tags (Quellwert bleibt `name`). */
return new class extends Migration {
    public function up(): void {
        Schema::table('procedure_templates', function (Blueprint $table): void {
            $table->json('name_i18n')->nullable()->after('name');
        });
        Schema::table('tags', function (Blueprint $table): void {
            $table->json('name_i18n')->nullable()->after('name');
        });
    }

    public function down(): void {
        Schema::table('procedure_templates', function (Blueprint $table): void {
            $table->dropColumn('name_i18n');
        });
        Schema::table('tags', function (Blueprint $table): void {
            $table->dropColumn('name_i18n');
        });
    }
};
