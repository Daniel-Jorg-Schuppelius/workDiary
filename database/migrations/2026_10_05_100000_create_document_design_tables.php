<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_05_100000_create_document_design_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 28 (Feature 076): PDF-Dokumentdesign und Firmenbogen.
 * Firmenbogen-Assets werden beim Upload zu einer sicheren, nicht interaktiven
 * Rasterrepräsentation normalisiert (Original bleibt als Nachweis erhalten).
 * Renderprofile sind versioniert; aktivierte Versionen sind unveränderlich.
 * Beim Finalisieren friert das Fachmodul den Layoutstand als Snapshot ein.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('letterhead_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('page_role', 12); // first | following
            $table->string('source_type', 8); // pdf | jpg | png
            $table->string('disk', 32);
            $table->string('original_path');
            $table->string('normalized_path')->nullable();
            $table->string('original_name');
            $table->string('mime', 64);
            $table->unsignedBigInteger('size');
            $table->decimal('width_mm', 6, 2)->nullable();
            $table->decimal('height_mm', 6, 2)->nullable();
            $table->char('original_sha256', 64);
            $table->char('normalized_sha256', 64)->nullable();
            $table->string('status', 20)->default('review_required');
            $table->json('review_notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'lh_assets_org_status_idx');
        });

        Schema::create('document_render_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 12)->default('draft');
            $table->boolean('is_default')->default(false);
            $table->json('document_kinds')->nullable();
            $table->string('locale', 10)->nullable();
            $table->smallInteger('priority')->default(0);
            $table->unsignedBigInteger('active_version_id')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status'], 'drp_org_status_idx');
        });

        Schema::create('document_render_profile_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_render_profile_id')
                ->constrained('document_render_profiles', indexName: 'drpv_profile_fk')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('status', 12)->default('draft'); // draft | active | superseded
            $table->foreignId('first_asset_id')->nullable()
                ->constrained('letterhead_assets', indexName: 'drpv_first_asset_fk')->restrictOnDelete();
            $table->foreignId('following_asset_id')->nullable()
                ->constrained('letterhead_assets', indexName: 'drpv_follow_asset_fk')->restrictOnDelete();
            $table->json('layout');
            $table->json('block_rules');
            $table->json('table_style');
            $table->char('checksum', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('activated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['document_render_profile_id', 'version'], 'drpv_profile_version_uq');
        });

        Schema::table('document_render_profiles', function (Blueprint $table): void {
            $table->foreign('active_version_id', 'drp_active_version_fk')
                ->references('id')->on('document_render_profile_versions')->nullOnDelete();
        });

        Schema::create('document_render_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_render_profile_id')->nullable()
                ->constrained('document_render_profiles', indexName: 'drs_profile_fk')->nullOnDelete();
            $table->foreignId('profile_version_id')->nullable()
                ->constrained('document_render_profile_versions', indexName: 'drs_version_fk')->nullOnDelete();
            $table->string('document_kind', 30);
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');
            $table->json('payload');
            $table->char('first_asset_sha256', 64)->nullable();
            $table->char('following_asset_sha256', 64)->nullable();
            $table->string('generator_version', 40);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['documentable_type', 'documentable_id', 'document_kind'], 'drs_doc_kind_uq');
            $table->index(['organization_id', 'document_kind'], 'drs_org_kind_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('document_render_snapshots');
        Schema::table('document_render_profiles', function (Blueprint $table): void {
            $table->dropForeign('drp_active_version_fk');
        });
        Schema::dropIfExists('document_render_profile_versions');
        Schema::dropIfExists('document_render_profiles');
        Schema::dropIfExists('letterhead_assets');
    }
};
