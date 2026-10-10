<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_13_100000_add_continuation_of_id_to_sick_leaves.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lohnfortzahlung (MVP-1093): Fortsetzungserkrankung nach § 3 Abs. 1 S. 2
 * EntgFG — dieselbe Krankheit wie eine frühere Krankmeldung, in der Regel
 * von der Krankenkasse bestätigt. Anders als `follow_up_for_id`
 * (Folgebescheinigung) liegt dazwischen Arbeitsfähigkeit.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('sick_leaves', function (Blueprint $table): void {
            $table->foreignId('continuation_of_id')->nullable()->after('follow_up_for_id')->constrained('sick_leaves')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('sick_leaves', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('continuation_of_id');
        });
    }
};
