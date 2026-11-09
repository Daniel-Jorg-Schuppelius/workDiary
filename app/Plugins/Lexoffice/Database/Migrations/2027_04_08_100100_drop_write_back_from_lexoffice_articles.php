<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_08_100100_drop_write_back_from_lexoffice_articles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Artikel-Rückschreiben an Lexoffice ohne Weg dorthin entfernt (MVP-1519): niemand setzte `is_dirty`, `last_pushed_at` schrieb nur der Push. */
return new class extends Migration {
    public function up(): void {
        Schema::table('lexoffice_articles', function (Blueprint $table): void {
            $table->dropColumn(['is_dirty', 'last_pushed_at']);
        });
    }

    public function down(): void {
        Schema::table('lexoffice_articles', function (Blueprint $table): void {
            $table->boolean('is_dirty')->default(false)->after('synced_at');
            $table->timestamp('last_pushed_at')->nullable()->after('is_dirty');
        });
    }
};
