<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_23_100000_create_knowledge_pages_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interne Wissensdatenbank je Organisation (Feature 191, MVP-1664–1668).
 *
 * Aufbau wie ein Notizbuch: Bereich (`knowledge_spaces`, optional mit
 * Leserecht je Rolle/Team/Person in `knowledge_space_readers`) → Rubrik
 * (`knowledge_sections`) → Seite mit Unterseiten (`knowledge_pages`,
 * `parent_id`). Jede gespeicherte Änderung ist eine Fassung
 * (`knowledge_page_versions`); Leserinnen und Leser sehen die zuletzt
 * freigegebene Fassung (`approved_version`). Pflichtlektüre
 * (`knowledge_page_assignments`) verlangt eine Lesebestätigung je Fassung
 * (`knowledge_page_reads`). Dazu Besuche für „geändert seit Ihrem letzten
 * Besuch“, Glossar, Suchlücken (ohne Personenbezug) und die Schritte einer
 * Bildanleitung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('knowledge_spaces', function (Blueprint $table): void {
            $table->id();
            $table->organizationFk();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_restricted')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'position'], 'ksp_org_position_idx');
        });

        Schema::create('knowledge_space_readers', function (Blueprint $table): void {
            $table->id();
            $table->organizationFk();
            $table->foreignId('knowledge_space_id')->constrained('knowledge_spaces')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('role_name', 125)->nullable();
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('knowledge_sections', function (Blueprint $table): void {
            $table->id();
            $table->organizationFk();
            $table->foreignId('knowledge_space_id')->constrained('knowledge_spaces')->cascadeOnDelete();
            $table->string('title', 180);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['knowledge_space_id', 'position'], 'ksec_space_position_idx');
        });

        Schema::create('knowledge_pages', function (Blueprint $table): void {
            $table->id();
            $table->organizationFk();
            // Ein Bereich mit Seiten (auch im Papierkorb) lässt sich nicht löschen.
            $table->foreignId('knowledge_space_id')->constrained('knowledge_spaces')->restrictOnDelete();
            $table->foreignId('knowledge_section_id')->nullable()->constrained('knowledge_sections')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('knowledge_pages')->nullOnDelete();
            $table->string('kind', 16)->default('page');
            $table->unsignedInteger('position')->default(0);
            $table->string('title', 180);
            $table->longText('body')->nullable();
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('approved_version')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('review_interval_months')->nullable();
            $table->date('next_review_on')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('review_warned_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->dateTime('valid_warned_at')->nullable();
            $table->boolean('is_emergency')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'knowledge_space_id', 'knowledge_section_id', 'parent_id'], 'kp_tree_idx');
            $table->index(['organization_id', 'status'], 'kp_status_idx');
            $table->index(['organization_id', 'next_review_on'], 'kp_review_idx');
            $table->index(['organization_id', 'valid_until'], 'kp_valid_idx');
            $table->index(['organization_id', 'deleted_at'], 'kp_trash_idx');
        });

        Schema::create('knowledge_page_versions', function (Blueprint $table): void {
            $table->id();
            $table->organizationFk();
            $table->foreignId('knowledge_page_id')->constrained('knowledge_pages')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title', 180);
            $table->longText('body')->nullable();
            $table->json('steps')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['knowledge_page_id', 'version'], 'kpv_page_version_uq');
        });

        Schema::create('knowledge_page_reads', function (Blueprint $table): void {
            $table->id();
            $table->organizationFk();
            $table->foreignId('knowledge_page_id')->constrained('knowledge_pages')->cascadeOnDelete();
            $table->foreignId('knowledge_page_version_id')->constrained('knowledge_page_versions')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('confirmed_at');
            $table->timestamps();

            $table->unique(['knowledge_page_id', 'version', 'user_id'], 'kpr_page_version_user_uq');
        });

        Schema::create('knowledge_page_assignments', function (Blueprint $table): void {
            $table->id();
            $table->organizationFk();
            $table->foreignId('knowledge_page_id')->constrained('knowledge_pages')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('role_name', 125)->nullable();
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->boolean('is_onboarding')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'kind'], 'kpa_org_kind_idx');
        });

        Schema::create('knowledge_page_visits', function (Blueprint $table): void {
            $table->id();
            $table->organizationFk();
            $table->foreignId('knowledge_page_id')->constrained('knowledge_pages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('seen_version');
            $table->dateTime('visited_at');

            $table->unique(['knowledge_page_id', 'user_id'], 'kpvis_page_user_uq');
        });

        Schema::create('knowledge_glossary_terms', function (Blueprint $table): void {
            $table->id();
            $table->organizationFk();
            $table->string('term', 120);
            $table->json('synonyms')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('knowledge_page_id')->nullable()->constrained('knowledge_pages')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'term'], 'kgt_org_term_idx');
        });

        // Suchlücken ohne Personenbezug: gezählt wird der Begriff, nicht wer ihn gesucht hat.
        Schema::create('knowledge_search_gaps', function (Blueprint $table): void {
            $table->id();
            $table->organizationFk();
            $table->string('term', 120);
            $table->unsignedInteger('searches')->default(1);
            $table->dateTime('first_searched_at');
            $table->dateTime('last_searched_at');
            $table->dateTime('dismissed_at')->nullable();

            $table->unique(['organization_id', 'term'], 'ksg_org_term_uq');
        });

        Schema::create('knowledge_guide_steps', function (Blueprint $table): void {
            $table->id();
            $table->organizationFk();
            $table->foreignId('knowledge_page_id')->constrained('knowledge_pages')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->text('body')->nullable();
            $table->foreignId('attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
            $table->json('markers')->nullable();
            $table->dateTime('redacted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['knowledge_page_id', 'position'], 'kgs_page_position_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('knowledge_guide_steps');
        Schema::dropIfExists('knowledge_search_gaps');
        Schema::dropIfExists('knowledge_glossary_terms');
        Schema::dropIfExists('knowledge_page_visits');
        Schema::dropIfExists('knowledge_page_assignments');
        Schema::dropIfExists('knowledge_page_reads');
        Schema::dropIfExists('knowledge_page_versions');
        Schema::dropIfExists('knowledge_pages');
        Schema::dropIfExists('knowledge_sections');
        Schema::dropIfExists('knowledge_space_readers');
        Schema::dropIfExists('knowledge_spaces');
    }
};
