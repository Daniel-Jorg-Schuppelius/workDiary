<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_102400_create_ticket_routing_rules_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 065, P3 (MVP-153): deterministische Routing-Regeln (Position
 * aufsteigend, erste zutreffende Regel je Aktionstyp) MIT Pflicht-
 * Protokoll („warum wurde angewendet") und OLA-Kennzeichnung am
 * SLA-Vertrag (internes Ziel je Team).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('ticket_routing_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'trr_org_fk')
                ->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('position');
            $table->json('conditions'); // kind/customer_id/priority/source/queue_id
            $table->json('actions');    // set_queue/set_priority/set_sla/set_team
            $table->unsignedInteger('version')->default(1);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'position'], 'trr_org_pos_idx');
        });

        Schema::create('ticket_rule_executions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'tre_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('ticket_routing_rule_id')
                ->constrained('ticket_routing_rules', indexName: 'tre_rule_fk')
                ->cascadeOnDelete();
            $table->foreignId('service_ticket_id')
                ->constrained('service_tickets', indexName: 'tre_ticket_fk')
                ->cascadeOnDelete();
            $table->unsignedInteger('rule_version');
            $table->json('matched_conditions');
            $table->json('applied_actions');
            $table->boolean('dry_run')->default(false);
            $table->timestamps();

            $table->index(['service_ticket_id'], 'tre_ticket_idx');
        });

        Schema::table('sla_contracts', function (Blueprint $table): void {
            $table->boolean('is_ola')->default(false); // internes Ziel (OLA)
            $table->foreignId('ola_team_id')->nullable()
                ->constrained('teams', indexName: 'slc_ola_team_fk')
                ->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('sla_contracts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ola_team_id');
            $table->dropColumn('is_ola');
        });
        Schema::dropIfExists('ticket_rule_executions');
        Schema::dropIfExists('ticket_routing_rules');
    }
};
