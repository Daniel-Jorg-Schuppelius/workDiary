<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_09_120000_create_datev_online_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-122: DATEV-Online — Verbindung je Organisation und Übertragungsjournal. */
return new class extends Migration {
    public function up(): void {
        Schema::create('datev_online_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique('dvoc_org_unique')->constrained('organizations', indexName: 'dvoc_org_fk')->cascadeOnDelete();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('scopes', 512)->nullable();
            $table->string('status', 16)->default('active');
            $table->string('datev_client_number', 40)->nullable();
            $table->string('datev_client_name', 191)->nullable();
            $table->boolean('is_documents_enabled')->default(false);
            $table->date('documents_since')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error', 300)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users', indexName: 'dvoc_conn_by_fk')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->foreignId('disconnected_by')->nullable()->constrained('users', indexName: 'dvoc_disc_by_fk')->nullOnDelete();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('datev_online_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'dvot_org_fk')->cascadeOnDelete();
            $table->string('kind', 24);
            $table->string('source_type', 100);
            $table->unsignedBigInteger('source_id');
            $table->string('datev_client_number', 40);
            $table->string('datev_reference', 100)->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('error', 300)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('transferred_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'kind', 'source_type', 'source_id'], 'dvot_source_unique');
            $table->index(['organization_id', 'status'], 'dvot_org_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('datev_online_transfers');
        Schema::dropIfExists('datev_online_connections');
    }
};
