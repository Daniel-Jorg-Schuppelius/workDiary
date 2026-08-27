<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_104300_add_tab_key_to_user_dashboard_widgets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Bereich (Tab), in dem eine Kachel steht. NULL = erster Bereich; wer gar
     * keine Bereiche angelegt hat, sieht wie bisher eine einzige Fläche.
     * Die Bereiche selbst (Schlüssel + Beschriftung) liegen als Liste in den
     * Nutzer-Präferenzen bzw. den Organisationseinstellungen — sie sind
     * Layout, keine eigene Entität.
     */
    public function up(): void {
        Schema::table('user_dashboard_widgets', function (Blueprint $table): void {
            $table->string('tab_key', 40)->nullable()->after('width');
        });
    }

    public function down(): void {
        Schema::table('user_dashboard_widgets', function (Blueprint $table): void {
            $table->dropColumn('tab_key');
        });
    }
};
