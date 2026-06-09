<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000017_make_whistleblowing_case_content_nullable.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Erlaubt das Nullen der Meldeinhalte bei der kontrollierten Loeschung
 * (Crypto-Shredding + Inhalt entfernen, Abschnitt 16). subject/description waren
 * bei der Erfassung pflicht, nach der Loeschung sind sie leer.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('whistleblowing_cases', function (Blueprint $table): void {
            $table->longText('subject_ciphertext')->nullable()->change();
            $table->longText('description_ciphertext')->nullable()->change();
        });
    }

    public function down(): void {
        Schema::table('whistleblowing_cases', function (Blueprint $table): void {
            $table->longText('subject_ciphertext')->nullable(false)->change();
            $table->longText('description_ciphertext')->nullable(false)->change();
        });
    }
};
