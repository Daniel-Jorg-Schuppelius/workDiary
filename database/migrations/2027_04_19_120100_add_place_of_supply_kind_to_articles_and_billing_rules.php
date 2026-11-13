<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_19_120100_add_place_of_supply_kind_to_articles_and_billing_rules.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1688: Leistungsort-Art (§ 3a UStG) an Artikel und Abrechnungsregel des
 * Projekts. Leer erbt: Regel → Artikel → Vorgabe der Organisation
 * (`invoicing.place_of_supply_kind`, ab Werk „allgemein“) — der Bestand
 * behält seinen Steuerfall.
 */
return new class extends Migration {
    /** @var list<string> */
    private const array TABLES = ['articles', 'project_billing_rules'];

    public function up(): void {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'place_of_supply_kind')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('place_of_supply_kind', 16)->nullable();
            });
        }
    }

    public function down(): void {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'place_of_supply_kind')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('place_of_supply_kind');
            });
        }
    }
};
