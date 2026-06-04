<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_04_121000_add_payroll_fields_to_users_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('payroll_hourly_wage', 10, 2)->nullable()->after('personnel_number');
            $table->string('tax_identification_number', 32)->nullable()->after('payroll_hourly_wage');
            $table->string('social_security_number', 64)->nullable()->after('tax_identification_number');
            $table->date('date_of_birth')->nullable()->after('social_security_number');
            $table->string('health_insurance', 128)->nullable()->after('date_of_birth');
            $table->string('tax_class', 16)->nullable()->after('health_insurance');
            $table->decimal('child_allowances', 4, 2)->nullable()->after('tax_class');
            $table->boolean('church_tax')->default(false)->after('child_allowances');
            $table->date('employment_start_date')->nullable()->after('church_tax');
            $table->date('employment_end_date')->nullable()->after('employment_start_date');
            // Wochenarbeitsstunden bewusst NICHT hier: Single Source of Truth ist
            // das Arbeitszeit-Modell (work_schedules.weekly_minutes).
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'payroll_hourly_wage',
                'tax_identification_number',
                'social_security_number',
                'date_of_birth',
                'health_insurance',
                'tax_class',
                'child_allowances',
                'church_tax',
                'employment_start_date',
                'employment_end_date',
            ]);
        });
    }
};
