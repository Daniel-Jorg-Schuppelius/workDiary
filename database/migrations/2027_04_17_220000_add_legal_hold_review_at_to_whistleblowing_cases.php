<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_220000_add_legal_hold_review_at_to_whistleblowing_cases.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prüfdatum der Löschsperre (MVP-1601). Rechtsgrundlage und Verantwortlicher
 * stehen verschlüsselt in der internen Notiz zum Statuswechsel.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('whistleblowing_cases', function (Blueprint $table): void {
            $table->date('legal_hold_review_at')->nullable()->after('legal_hold_at');
        });
    }

    public function down(): void {
        Schema::table('whistleblowing_cases', function (Blueprint $table): void {
            $table->dropColumn('legal_hold_review_at');
        });
    }
};
