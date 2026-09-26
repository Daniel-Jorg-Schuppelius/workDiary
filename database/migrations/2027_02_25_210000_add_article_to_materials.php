<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_25_210000_add_article_to_materials.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-904: optionale Verknüpfung Material → Artikel (Lieferantenbezug über den Artikel). */
return new class extends Migration {
    public function up(): void {
        Schema::table('materials', function (Blueprint $table): void {
            $table->foreignId('article_id')->nullable()->after('organization_id')->constrained('articles')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('materials', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('article_id');
        });
    }
};
