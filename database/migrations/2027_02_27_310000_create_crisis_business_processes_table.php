<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_310000_create_crisis_business_processes_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-943: fallunabhängiges BIA-Register (Geschäftsprozesse mit Kritikalität und Wiederanlaufzielen). */
return new class extends Migration {
    public function up(): void {
        Schema::create('crisis_business_processes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'crisis_bp_org_fk')->cascadeOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users', indexName: 'crisis_bp_owner_fk')->nullOnDelete();
            $table->string('criticality', 16);
            $table->unsignedInteger('rto_hours')->nullable();
            $table->unsignedInteger('rpo_hours')->nullable();
            $table->unsignedInteger('mtpd_hours')->nullable();
            $table->text('dependencies')->nullable();
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->date('review_due_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'crisis_bp_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_type', 'source_id'], 'crisis_bp_source_idx');
        });

        Schema::table('crisis_continuity_impacts', function (Blueprint $table): void {
            $table->foreignId('crisis_business_process_id')->nullable()->after('crisis_case_id')
                ->constrained('crisis_business_processes', indexName: 'cci_business_process_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('crisis_continuity_impacts', function (Blueprint $table): void {
            $table->dropForeign('cci_business_process_fk');
            $table->dropColumn('crisis_business_process_id');
        });
        Schema::dropIfExists('crisis_business_processes');
    }
};
