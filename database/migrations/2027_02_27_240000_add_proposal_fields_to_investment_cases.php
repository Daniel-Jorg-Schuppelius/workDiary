<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_240000_add_proposal_fields_to_investment_cases.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-936: Investitionsvorschläge von Mitarbeitenden und über einen öffentlichen Link. */
return new class extends Migration {
    public function up(): void {
        Schema::table('investment_cases', function (Blueprint $table): void {
            $table->string('origin', 16)->nullable()->after('status');
            $table->foreignId('submitter_user_id')->nullable()->after('origin')
                ->constrained('users', indexName: 'inv_cases_submitter_fk')->nullOnDelete();
            $table->string('submitter_name', 200)->nullable()->after('submitter_user_id');
            $table->string('submitter_email', 255)->nullable()->after('submitter_name');
            $table->decimal('estimated_amount', 14, 2)->nullable()->after('submitter_email');
            $table->string('currency', 3)->nullable()->after('estimated_amount');
        });
    }

    public function down(): void {
        Schema::table('investment_cases', function (Blueprint $table): void {
            $table->dropForeign('inv_cases_submitter_fk');
            $table->dropColumn(['origin', 'submitter_user_id', 'submitter_name', 'submitter_email', 'estimated_amount', 'currency']);
        });
    }
};
