<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_104200_add_width_to_user_dashboard_widgets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Kachelbreite je Nutzer und Kachel. NULL = Vorgabe der Widget-Klasse
     * (Widget::defaultWidth), damit spätere Änderungen an der Vorgabe bei
     * Nutzern durchschlagen, die die Breite nie angefasst haben.
     */
    public function up(): void {
        Schema::table('user_dashboard_widgets', function (Blueprint $table): void {
            $table->string('width', 8)->nullable()->after('sort_order');
        });
    }

    public function down(): void {
        Schema::table('user_dashboard_widgets', function (Blueprint $table): void {
            $table->dropColumn('width');
        });
    }
};
