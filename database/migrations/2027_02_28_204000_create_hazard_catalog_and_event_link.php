<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_204000_create_hazard_catalog_and_event_link.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-1002: Gefährdungskatalog der Organisation, Sicherheitsereignis verweist auf die GBU. */
return new class extends Migration {
    public function up(): void {
        Schema::create('hazard_catalog_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('category', 120);
            $table->string('hazard', 255);
            $table->text('measure')->nullable();
            $table->unsignedTinyInteger('severity');
            $table->unsignedTinyInteger('likelihood');
            $table->string('source_profile', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'code'], 'hazcat_org_code_uq');
        });

        Schema::table('safety_events', function (Blueprint $table): void {
            $table->foreignId('hazard_assessment_id')->nullable()->after('subject_id')->constrained('hazard_assessments')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('safety_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hazard_assessment_id');
        });
        Schema::dropIfExists('hazard_catalog_items');
    }
};
