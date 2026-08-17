<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_102800_add_text_complements_to_boq_items.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 108, MVP-618: Textergänzungen einer Position (GAEB TextComplement).
 * Ihre Nummern sind bei der Angebotsabgabe unverändert zurückzugeben, und
 * ava-sign prüft beim Reimport, ob alle Lücken gefüllt sind — sie dürfen daher
 * nicht im Fließtext verschwinden.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('boq_items', function (Blueprint $table): void {
            $table->json('text_complements')->nullable()->after('sub_descriptions');
        });
    }

    public function down(): void {
        Schema::table('boq_items', function (Blueprint $table): void {
            $table->dropColumn('text_complements');
        });
    }
};
