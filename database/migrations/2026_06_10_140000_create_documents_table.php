<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_10_140000_create_documents_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Polymorpher Bezug (Customer|Project|DiaryEntry|Asset) —
            // nullable für freie, nicht zugeordnete Dokumente des Mandanten.
            $table->string('documentable_type', 64)->nullable();
            $table->unsignedBigInteger('documentable_id')->nullable();
            $table->string('title', 180);
            $table->string('document_type', 24);
            $table->string('status', 16)->default('active');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            // Zeiger auf die aktuelle Version. Bewusst OHNE FK-Constraint:
            // documents ↔ document_versions wären sonst zirkulär referenziert
            // (Henne-Ei beim Anlegen und beim Droppen der Tabellen).
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'documents_org_status_idx');
            $table->index(['organization_id', 'document_type'], 'documents_org_type_idx');
            $table->index(['organization_id', 'valid_until'], 'documents_org_valid_idx');
            $table->index(['documentable_type', 'documentable_id'], 'documents_documentable_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('documents');
    }
};
