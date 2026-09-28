<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_160000_create_personnel_file_acknowledgements_and_submissions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-987: Lesebestätigung je Dokumentversion und Einreichungen der Mitarbeitenden zur Personalakte. */
return new class extends Migration {
    public function up(): void {
        Schema::table('documents', function (Blueprint $table): void {
            $table->boolean('is_ack_required')->default(false)->after('retention_until');
        });

        Schema::create('personnel_file_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'pf_ack_org_fk')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents', indexName: 'pf_ack_document_fk')->cascadeOnDelete();
            $table->foreignId('document_version_id')->constrained('document_versions', indexName: 'pf_ack_version_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users', indexName: 'pf_ack_user_fk')->cascadeOnDelete();
            $table->timestamp('acknowledged_at');
            $table->timestamps();
            $table->unique(['document_id', 'document_version_id'], 'pf_ack_document_version_unique');
        });

        Schema::create('personnel_file_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'pf_sub_org_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users', indexName: 'pf_sub_user_fk')->cascadeOnDelete();
            $table->string('title', 180);
            $table->string('hr_category', 32);
            $table->string('note', 500)->nullable();
            $table->string('disk', 32);
            $table->string('path', 255)->nullable();
            $table->string('original_name', 255);
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size');
            $table->string('status', 20);
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users', indexName: 'pf_sub_reviewer_fk')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents', indexName: 'pf_sub_document_fk')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'status'], 'pf_sub_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('personnel_file_submissions');
        Schema::dropIfExists('personnel_file_acknowledgements');
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn('is_ack_required');
        });
    }
};
