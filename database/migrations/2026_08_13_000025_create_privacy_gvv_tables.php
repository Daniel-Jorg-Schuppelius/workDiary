<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_13_000025_create_privacy_gvv_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vereinbarungen gemeinsam Verantwortlicher (GVV, Art. 26) mit
 * Zuständigkeitsmatrix und Verknuepfung zu Verarbeitungstaetigkeiten.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('privacy_joint_controller_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('privacy_processors')->cascadeOnDelete();
            $table->string('title');
            $table->string('version', 32)->default('1.0');
            $table->string('status', 16)->default('draft');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->date('review_due_at')->nullable();
            // Zustaendigkeitsmatrix: { information_duties, data_subject_rights,
            // incidents, authority_contact } => 'us' | 'partner' | 'joint'
            $table->json('responsibilities')->nullable();
            $table->string('contact_point')->nullable();          // gemeinsame Anlaufstelle
            $table->boolean('essence_provided')->default(false);   // Wesentliches den Betroffenen bereitgestellt
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('privacy_gvv_activity', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gvv_id')->constrained('privacy_joint_controller_agreements')->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained('privacy_processing_activities')->cascadeOnDelete();

            $table->unique(['gvv_id', 'activity_id'], 'pgvva_gvv_activity_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('privacy_gvv_activity');
        Schema::dropIfExists('privacy_joint_controller_agreements');
    }
};
