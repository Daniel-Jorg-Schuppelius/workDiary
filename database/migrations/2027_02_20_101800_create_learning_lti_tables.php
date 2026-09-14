<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_20_101800_create_learning_lti_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LTI 1.3 für die Lernplattform (Feature 149, Phase 9).
 *
 * Als Plattform startet WorkDiary fremde Tools (Tools und Links), als Tool nimmt
 * es Starts fremder Plattformen an (Plattformen und Subjekte). Schlüssel und
 * Nonces gehören der Instanz: Aussteller ist die Anwendung, nicht die Organisation.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_lti_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('kid', 64);
            $table->text('public_jwk');
            // Nur verschlüsselt (Cast `encrypted:array`).
            $table->text('private_jwk');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->unique('kid', 'lrn_lti_key_kid_uq');
        });

        Schema::create('learning_lti_tools', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 150);
            // Als Plattform vergibt WorkDiary Client- und Deployment-ID selbst.
            $table->string('client_id', 100);
            $table->string('deployment_id', 100);
            $table->string('login_url', 2000);
            $table->string('launch_url', 2000);
            $table->json('redirect_uris');
            $table->string('deep_linking_url', 2000)->nullable();
            // Schlüssel des Tools: JWKS-Adresse oder fest hinterlegte Schlüsselmenge.
            $table->string('jwks_url', 2000)->nullable();
            $table->text('public_jwks')->nullable();
            $table->boolean('share_name')->default(false);
            $table->boolean('share_email')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('client_id', 'lrn_lti_tool_client_uq');
        });

        Schema::create('learning_lti_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_unit_id')->constrained('learning_units')->cascadeOnDelete();
            $table->foreignId('learning_lti_tool_id')->constrained('learning_lti_tools')->cascadeOnDelete();
            $table->uuid('resource_link_id');
            $table->string('title', 255)->nullable();
            // Ziel aus Deep Linking; ohne eigenes Ziel gilt die Start-Adresse des Tools.
            $table->string('url', 2000)->nullable();
            $table->json('custom')->nullable();
            $table->timestamps();

            $table->unique('learning_unit_id', 'lrn_lti_link_unit_uq');
            $table->unique('resource_link_id', 'lrn_lti_link_res_uq');
        });

        Schema::create('learning_lti_platforms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('issuer', 500);
            $table->string('client_id', 255);
            // Abdruck über Aussteller und Client-ID — eindeutig ohne überlangen Index.
            $table->string('lookup_hash', 64);
            $table->json('deployment_ids');
            $table->string('authorization_endpoint', 2000);
            $table->string('jwks_url', 2000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('lookup_hash', 'lrn_lti_plat_lookup_uq');
        });

        Schema::create('learning_lti_nonces', function (Blueprint $table): void {
            $table->id();
            $table->string('nonce_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique('nonce_hash', 'lrn_lti_nonce_uq');
            $table->index('expires_at', 'lrn_lti_nonce_exp_idx');
        });

        Schema::create('learning_lti_subjects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_lti_platform_id')->constrained('learning_lti_platforms')->cascadeOnDelete();
            // `sub` der Plattform nur als Abdruck.
            $table->string('subject_hash', 64);
            $table->foreignId('external_participant_id')->nullable()->constrained('external_participants')->nullOnDelete();
            $table->timestamps();

            $table->unique(['learning_lti_platform_id', 'subject_hash'], 'lrn_lti_subj_uq');
        });

        Schema::table('learning_courses', function (Blueprint $table): void {
            // Über LTI startbar nur, was die Organisation ausdrücklich freigibt.
            $table->boolean('lti_available')->default(false);
        });
    }

    public function down(): void {
        Schema::table('learning_courses', function (Blueprint $table): void {
            $table->dropColumn('lti_available');
        });

        Schema::dropIfExists('learning_lti_subjects');
        Schema::dropIfExists('learning_lti_nonces');
        Schema::dropIfExists('learning_lti_platforms');
        Schema::dropIfExists('learning_lti_links');
        Schema::dropIfExists('learning_lti_tools');
        Schema::dropIfExists('learning_lti_keys');
    }
};
