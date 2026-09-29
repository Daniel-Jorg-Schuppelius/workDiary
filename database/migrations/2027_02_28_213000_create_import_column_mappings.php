<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_213000_create_import_column_mappings.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-1020: gespeicherte Spaltenzuordnungen des Imports je Organisation und Importart. */
return new class extends Migration {
    public function up(): void {
        Schema::create('import_column_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('entity', 40);
            $table->string('source_header', 191);
            $table->string('target_column', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'entity', 'source_header'], 'import_col_map_org_entity_header_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('import_column_mappings');
    }
};
