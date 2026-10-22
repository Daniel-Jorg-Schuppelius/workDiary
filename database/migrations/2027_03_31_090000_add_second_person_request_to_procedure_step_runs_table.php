<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_090000_add_second_person_request_to_procedure_step_runs_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vier-Augen-Schritt anfordern (MVP-1301): Stand der Anforderung am Schritt,
 * damit Karte und Selbstausschluss sie ohne Journal-Abfrage kennen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('procedure_step_runs', function (Blueprint $table): void {
            $table->timestamp('second_person_requested_at')->nullable()->after('second_person_signed_at');
            $table->foreignId('second_person_requester_user_id')->nullable()->after('second_person_requested_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('procedure_step_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('second_person_requester_user_id');
            $table->dropColumn('second_person_requested_at');
        });
    }
};
