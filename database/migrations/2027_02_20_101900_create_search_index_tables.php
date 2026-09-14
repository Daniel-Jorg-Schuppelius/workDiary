<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_20_101900_create_search_index_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tätigkeitsrecherche (Feature 153, MVP-770/772).
 *
 * `search_documents` ist abgeleitet: eine Zeile je Quelle mit denormalisiertem
 * Kontext (Kunde, Endkunde, Projekt, Person) und kodiertem Suchtext — jederzeit
 * über `search:rebuild` neu aufbaubar, deshalb ohne Fremdschlüssel außer der
 * Organisation. Der Volltextindex existiert nur auf MySQL/MariaDB; SQLite sucht
 * mit LIKE auf derselben Kodierung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('search_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id');
            $table->timestamp('occurred_at')->nullable();
            $table->boolean('date_only')->default(false);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('foreign_customer_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedInteger('minutes')->nullable();
            $table->boolean('restricted')->default(false);
            $table->string('title', 255)->default('');
            $table->text('excerpt')->nullable();
            $table->mediumText('search_text');
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id'], 'sdoc_source_unique');
            $table->index(['organization_id', 'occurred_at'], 'sdoc_org_occurred_idx');
            $table->index(['organization_id', 'customer_id'], 'sdoc_org_customer_idx');
            $table->index(['organization_id', 'foreign_customer_id'], 'sdoc_org_fcustomer_idx');
            $table->index(['organization_id', 'project_id'], 'sdoc_org_project_idx');
            $table->index(['organization_id', 'source_type', 'user_id'], 'sdoc_org_type_user_idx');
        });

        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('search_documents', function (Blueprint $table): void {
                $table->fullText('search_text', 'sdoc_search_ft');
            });
        }

        Schema::create('search_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('term', 40);
            $table->unique(['organization_id', 'term'], 'sterm_org_term_unique');
        });

        Schema::create('search_synonym_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->text('terms');
            $table->boolean('active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'active'], 'ssyn_org_active_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('search_synonym_groups');
        Schema::dropIfExists('search_terms');
        Schema::dropIfExists('search_documents');
    }
};
