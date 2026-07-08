<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_25_100000_create_asset_ownership_changes_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unveränderliche Eigentümerwechsel-Historie eines Assets (Feature 027 →
 * Rang 49). Append-only: jede Zuordnungsänderung (Eigentümerschaft und/oder
 * Kunde) wird über {@see \App\Services\Asset\AssetLifecycleService::changeOwnership}
 * als neue Zeile festgehalten — nie roh aktualisiert.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('asset_ownership_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'assetown_org_fk')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets', indexName: 'assetown_asset_fk')->cascadeOnDelete();
            $table->string('from_ownership', 20)->nullable();
            $table->string('to_ownership', 20);
            $table->foreignId('from_customer_id')->nullable()->constrained('customers', indexName: 'assetown_fromcust_fk')->nullOnDelete();
            $table->foreignId('to_customer_id')->nullable()->constrained('customers', indexName: 'assetown_tocust_fk')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users', indexName: 'assetown_by_fk')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['asset_id', 'changed_at'], 'assetown_asset_time_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('asset_ownership_changes');
    }
};
