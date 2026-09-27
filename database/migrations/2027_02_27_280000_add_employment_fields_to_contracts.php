<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_280000_add_employment_fields_to_contracts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-939: Arbeitsvertrag über die Signaturschicht — Bezug zu Teammitglied oder Bewerbung. */
return new class extends Migration {
    public function up(): void {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->foreignId('employee_user_id')->nullable()->after('supplier_id')
                ->constrained('users', indexName: 'contracts_employee_fk')->nullOnDelete();
            $table->foreignId('job_application_id')->nullable()->after('employee_user_id')
                ->constrained('job_applications', indexName: 'contracts_job_application_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropForeign('contracts_employee_fk');
            $table->dropForeign('contracts_job_application_fk');
            $table->dropColumn(['employee_user_id', 'job_application_id']);
        });
    }
};
