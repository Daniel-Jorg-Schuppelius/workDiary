<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_04_100000_add_base_kind_to_invoice_retentions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bemessungsgrundlage am Sicherheitseinbehalt (Feature 113, MVP-602).
 *
 * Der erste Wurf rechnete immer vom Bruttobetrag. Die häufigere
 * Vertragsklausel ist aber der Nettobetrag — und welche galt, muss am Beleg
 * ablesbar sein, nicht im Kopf des Erfassers. `gross` als Spalten-Default
 * hält bereits erfasste Einbehalte bei ihrer Rechnung; neu erfasste kommen
 * über die Oberfläche mit `net` vorbelegt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('invoice_retentions', function (Blueprint $table): void {
            $table->string('base_kind', 8)->default('gross')->after('percent');
        });
    }

    public function down(): void {
        Schema::table('invoice_retentions', function (Blueprint $table): void {
            $table->dropColumn('base_kind');
        });
    }
};
