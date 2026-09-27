<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_300000_add_authority_report_fields_to_recalls.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-945: Angaben für die Behördenmeldung eines Rückrufs. */
return new class extends Migration {
    public function up(): void {
        Schema::table('recalls', function (Blueprint $table): void {
            $table->string('hazard_kind', 40)->nullable()->after('reason');
            $table->text('hazard_description')->nullable()->after('hazard_kind');
            $table->string('risk_level', 16)->nullable()->after('hazard_description');
            $table->string('measure', 20)->nullable()->after('risk_level');
            $table->json('countries')->nullable()->after('measure');
            $table->string('authority_name', 200)->nullable()->after('countries');
            $table->string('authority_reference', 100)->nullable()->after('authority_name');
            $table->date('authority_reported_on')->nullable()->after('authority_reference');
            $table->string('contact_name', 200)->nullable()->after('authority_reported_on');
            $table->string('contact_email', 255)->nullable()->after('contact_name');
        });
    }

    public function down(): void {
        Schema::table('recalls', function (Blueprint $table): void {
            $table->dropColumn(['hazard_kind', 'hazard_description', 'risk_level', 'measure', 'countries', 'authority_name', 'authority_reference', 'authority_reported_on', 'contact_name', 'contact_email']);
        });
    }
};
