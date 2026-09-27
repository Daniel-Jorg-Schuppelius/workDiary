<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_170000_create_job_interview_offers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-925: Terminangebote an Bewerber mit Auswahl über einen Link. */
return new class extends Migration {
    public function up(): void {
        Schema::create('job_interview_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'job_iv_offers_org_fk')->cascadeOnDelete();
            $table->foreignId('job_application_id')->constrained('job_applications', indexName: 'job_iv_offers_app_fk')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique('job_iv_offers_token_uq');
            $table->json('slots');
            $table->string('mode', 16);
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->foreignId('interviewer_user_id')->nullable()->constrained('users', indexName: 'job_iv_offers_interviewer_fk')->nullOnDelete();
            $table->dateTime('expires_at');
            $table->dateTime('chosen_at')->nullable();
            $table->foreignId('job_application_interview_id')->nullable()->constrained('job_application_interviews', indexName: 'job_iv_offers_interview_fk')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'job_iv_offers_created_by_fk')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('job_interview_offers');
    }
};
