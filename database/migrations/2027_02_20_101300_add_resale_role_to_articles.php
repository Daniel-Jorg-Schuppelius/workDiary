<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_20_101300_add_resale_role_to_articles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 152 (Review 2026-09-11): Einstufung je lokalem Artikel für das
 * Reselling-Register — dieselbe Spalte wie an `lexoffice_articles`
 * (null = automatisch, 'license' = Abo-Produkt, 'excluded' = nie Abo-Position).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('articles', function (Blueprint $t): void {
            $t->string('resale_role', 16)->nullable()->after('status');
        });
    }

    public function down(): void {
        Schema::table('articles', function (Blueprint $t): void {
            $t->dropColumn('resale_role');
        });
    }
};
