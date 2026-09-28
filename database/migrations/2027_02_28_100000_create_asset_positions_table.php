<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_100000_create_asset_positions_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-975: Positionsjournal je Gerät (Telematik-Import) mit Soll-Ort und Abweichung. */
return new class extends Migration {
    public function up(): void {
        Schema::create('asset_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'asset_pos_org_fk')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets', indexName: 'asset_pos_asset_fk')->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('source', 20)->default('import');
            $table->string('expected_label', 200)->nullable();
            $table->unsignedInteger('deviation_m')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'asset_pos_creator_fk')->nullOnDelete();
            $table->timestamps();
            $table->unique(['asset_id', 'recorded_at'], 'asset_pos_asset_time_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('asset_positions');
    }
};
