<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_170000_add_response_due_days_to_claim_supplier_recourses.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1577: Antwortfrist je Regressfall. Die Frist wird beim Anlegen
 * festgehalten und beim Einreichen in `response_due_at` umgerechnet; ohne
 * Wert gilt die Standardfrist.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('claim_supplier_recourses', function (Blueprint $table): void {
            $table->unsignedSmallInteger('response_due_days')->nullable()->after('submitted_at');
        });
    }

    public function down(): void {
        Schema::table('claim_supplier_recourses', function (Blueprint $table): void {
            $table->dropColumn('response_due_days');
        });
    }
};
