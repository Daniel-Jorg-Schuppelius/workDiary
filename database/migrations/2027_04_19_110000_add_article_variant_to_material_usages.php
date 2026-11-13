<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_19_110000_add_article_variant_to_material_usages.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1690: Hat der lagergeführte Artikel eines Materials mehrere Varianten,
 * wählt der Verbrauch die verbrauchte; leer bucht er wie bisher die
 * Standardvariante. Bestandszeilen bleiben leer.
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasColumn('material_usages', 'article_variant_id')) {
            return;
        }

        Schema::table('material_usages', function (Blueprint $table): void {
            $table->foreignId('article_variant_id')->nullable()->after('material_id')
                ->constrained('article_variants')->nullOnDelete();
        });
    }

    public function down(): void {
        if (! Schema::hasColumn('material_usages', 'article_variant_id')) {
            return;
        }

        Schema::table('material_usages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('article_variant_id');
        });
    }
};
