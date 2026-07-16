<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_21_100200_create_domain_command_and_dns_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schreibende Provider-Befehle als dedizierte Outbox + DNS-Zonen-/Record-
 * Projektionen (Feature 083, MVP-388/389/390/391).
 *
 * `domain_provider_commands` trägt idempotente Command-ID, Preflight-Snapshot,
 * Payload-Hash, Freigaben (Vier-Augen für Hochrisiko), Status und die
 * REDIGIERTE Providerantwort. Ausgang ohne vollständiges `EOF` bleibt
 * `unknown` und wird reconciled, nie blind wiederholt.
 *
 * Präfixe `dpx_`/`ddz_`/`ddr_`.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('domain_provider_commands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('domain_provider_connections', indexName: 'dpx_connection_fk')->cascadeOnDelete();

            $table->uuid('command_id'); // lokale idempotente Command-ID
            $table->string('capability', 32);  // domains|contacts|dns|renewal|transfer|dangerous
            $table->string('command', 64);     // Provider-Befehl (AddDomain, DeleteDomain, …)
            $table->string('target', 253)->nullable();
            $table->nullableMorphs('subject', 'dpx_subject_idx'); // DomainProjection etc.
            $table->foreignId('customer_id')->nullable()->constrained('customers', indexName: 'dpx_customer_fk')->nullOnDelete();

            $table->json('payload')->nullable();            // redigierte Parameter
            $table->json('preflight_snapshot')->nullable(); // gelesener Zustand vor der Aktion
            $table->char('payload_hash', 64);

            $table->string('status', 16)->default('draft');
            $table->boolean('requires_second_approval')->default(false);

            $table->foreignId('requested_by_user_id')->nullable()->constrained('users', indexName: 'dpx_requester_fk')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users', indexName: 'dpx_approver_fk')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();

            $table->string('provider_code', 16)->nullable();
            $table->text('provider_response')->nullable(); // redigiert
            $table->timestamp('reconciled_at')->nullable();
            $table->string('reconciliation_note', 300)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 300)->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'command_id'], 'dpx_org_cmdid_uq');
            $table->index(['organization_id', 'status'], 'dpx_org_status_idx');
            $table->index(['connection_id', 'command'], 'dpx_conn_cmd_idx');
        });

        Schema::create('domain_dns_zone_projections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('domain_provider_connections', indexName: 'ddz_connection_fk')->cascadeOnDelete();
            $table->foreignId('domain_projection_id')->nullable()->constrained('domain_projections', indexName: 'ddz_domain_fk')->nullOnDelete();

            $table->string('zone', 253);
            $table->char('zone_hash', 64);
            $table->json('soa')->nullable();
            $table->string('revision', 190)->nullable();
            $table->string('raw_hash', 64)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'connection_id', 'zone_hash'], 'ddz_org_conn_zonehash_uq');
        });

        Schema::create('domain_dns_record_projections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('domain_dns_zone_projections', indexName: 'ddr_zone_fk')->cascadeOnDelete();

            $table->string('type', 10);
            $table->string('name', 253);
            $table->unsignedInteger('ttl')->nullable();
            $table->unsignedInteger('priority')->nullable();
            $table->string('content', 1024);
            $table->string('raw', 1024)->nullable(); // serialisiertes RR-Format
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['zone_id', 'type'], 'ddr_zone_type_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('domain_dns_record_projections');
        Schema::dropIfExists('domain_dns_zone_projections');
        Schema::dropIfExists('domain_provider_commands');
    }
};
