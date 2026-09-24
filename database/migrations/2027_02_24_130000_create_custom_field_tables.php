<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_24_130000_create_custom_field_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-868 Benutzerdefinierte Felder: je Organisation und Träger (Morph-
 * Alias) ein Feldschema, je Datensatz ein Wertesatz.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('custom_field_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('subject_alias', 64);
            $table->json('schema');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'subject_alias'], 'cfd_org_subject_unique');
        });
        Schema::create('custom_field_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id');
            $table->json('values');
            $table->unsignedInteger('schema_version')->default(1);
            $table->timestamps();
            $table->unique(['subject_type', 'subject_id'], 'cfv_subject_unique');
            $table->index(['organization_id', 'subject_type'], 'cfv_org_subject_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_field_definitions');
    }
};
