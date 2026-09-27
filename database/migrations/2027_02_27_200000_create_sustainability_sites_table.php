<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_200000_create_sustainability_sites_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-929: eigene Standorte mit Bezugsgrößen für das Nachhaltigkeits-Benchmarking. */
return new class extends Migration {
    public function up(): void {
        Schema::create('sustainability_sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'sust_sites_org_fk')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('code', 40)->nullable();
            $table->decimal('area_m2', 12, 2)->nullable();
            $table->unsignedInteger('headcount')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'sust_sites_created_by_fk')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('sustainability_sites');
    }
};
