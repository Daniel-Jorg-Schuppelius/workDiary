<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_100100_create_business_service_dependencies_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Servicelandkarte (Feature 065, MVP-1256): ein Business-Service hängt von
 * anderen ab; fällt einer aus, sind alle abhängigen betroffen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('business_service_dependencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'bsd_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('business_service_id')
                ->constrained('business_services', indexName: 'bsd_service_fk')
                ->cascadeOnDelete();
            $table->foreignId('depends_on_business_service_id')
                ->constrained('business_services', indexName: 'bsd_depends_on_fk')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['business_service_id', 'depends_on_business_service_id'], 'bsd_pair_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('business_service_dependencies');
    }
};
