<?php
/*
 * Created on   : Tue Sep 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_20_100900_add_parent_id_to_resale_subscriptions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 152: Lizenzabtretung — ein Teil der Lizenzen eines Vertrags wird
 * an einen anderen Halter abgetreten (zwei Firmen im selben Haus). Das
 * Kind-Abo trägt Halter und Menge der abgetretenen Lizenzen und zeigt auf
 * den Vertrag; der Vertrag plant seine Perioden mit der verbleibenden Menge.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('resale_subscriptions', function (Blueprint $t): void {
            $t->foreignId('parent_id')->nullable()->after('successor_id')->constrained('resale_subscriptions', 'id', 'rs_parent_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('resale_subscriptions', function (Blueprint $t): void {
            $t->dropForeign('rs_parent_fk');
            $t->dropColumn('parent_id');
        });
    }
};
