<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000030_add_authority_reporting_to_incidents.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('privacy_incidents', function (Blueprint $table): void {
            $table->string('authority_key', 32)->nullable()->after('authority_notified_at');
            $table->string('authority_name')->nullable()->after('authority_key');
            $table->text('authority_portal_url')->nullable()->after('authority_name');
            $table->string('authority_report_type', 16)->nullable()->after('authority_portal_url');
            $table->string('authority_report_reference')->nullable()->after('authority_report_type');
            $table->string('authority_case_number')->nullable()->after('authority_report_reference');
        });
    }

    public function down(): void {
        Schema::table('privacy_incidents', function (Blueprint $table): void {
            $table->dropColumn([
                'authority_name',
                'authority_key',
                'authority_portal_url',
                'authority_report_type',
                'authority_report_reference',
                'authority_case_number',
            ]);
        });
    }
};
