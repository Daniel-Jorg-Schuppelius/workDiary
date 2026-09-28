<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_380000_create_sustainability_offsets_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-961: Zertifikate, Kompensationen und Klimabeiträge — getrennt von den Emissionen. */
return new class extends Migration {
    public function up(): void {
        Schema::create('sustainability_offsets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'sust_offsets_org_fk')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('provider', 200);
            $table->string('standard', 100)->nullable();
            $table->string('project_name', 200)->nullable();
            $table->decimal('quantity_t', 12, 3);
            $table->unsignedSmallInteger('vintage_year')->nullable();
            $table->unsignedSmallInteger('claim_year');
            $table->date('retired_on')->nullable();
            $table->string('registry_reference', 200)->nullable();
            $table->string('note', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'sust_offsets_creator_fk')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('sustainability_offsets');
    }
};
