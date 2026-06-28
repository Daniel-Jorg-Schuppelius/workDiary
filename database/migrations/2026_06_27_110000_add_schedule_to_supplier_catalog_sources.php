<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_27_110000_add_schedule_to_supplier_catalog_sources.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geplanter Katalogabruf (Feature 050, MVP-091): Abrufintervall und nächster
 * geplanter Lauf je Remote-Quelle. Ein Cron-Command zieht fällige Quellen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('supplier_catalog_sources', function (Blueprint $table): void {
            $table->unsignedInteger('fetch_interval_minutes')->nullable()->after('source_type');
            $table->timestamp('next_fetch_at')->nullable()->after('last_imported_at');
        });
    }

    public function down(): void {
        Schema::table('supplier_catalog_sources', function (Blueprint $table): void {
            $table->dropColumn(['fetch_interval_minutes', 'next_fetch_at']);
        });
    }
};
