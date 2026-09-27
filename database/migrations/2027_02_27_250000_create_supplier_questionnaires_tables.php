<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_250000_create_supplier_questionnaires_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-937: Lieferanten-Selbstauskunft — Fragebögen und Anfragen mit Einmal-Link. */
return new class extends Migration {
    public function up(): void {
        Schema::create('supplier_questionnaires', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'sup_quest_org_fk')->cascadeOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->json('schema');
            $table->unsignedSmallInteger('validity_months')->default(12);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'sup_quest_created_by_fk')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('supplier_questionnaire_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'sup_quest_req_org_fk')->cascadeOnDelete();
            $table->foreignId('supplier_questionnaire_id')->constrained('supplier_questionnaires', indexName: 'sup_quest_req_quest_fk')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers', indexName: 'sup_quest_req_supplier_fk')->cascadeOnDelete();
            $table->string('status', 16);
            $table->string('token_hash', 128)->unique('sup_quest_req_token_uq');
            $table->string('recipient_email', 255);
            $table->json('schema_snapshot');
            $table->json('answers')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('submitted_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users', indexName: 'sup_quest_req_reviewed_by_fk')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'sup_quest_req_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'status'], 'sup_quest_req_supplier_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('supplier_questionnaire_requests');
        Schema::dropIfExists('supplier_questionnaires');
    }
};
