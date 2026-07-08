<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_102200_extend_service_tickets_for_helpdesk.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 065, P1 (MVP-151): Ticketart/Requester/Impact/Urgency/
 * Wartezustand/Abschlusscode am bestehenden Ticket (kein zweites
 * Ticketmodell), Watcher-Liste, SLA-Uhr-Segmente für reproduzierbare
 * Fristen und pausierende Gründe am SLA-Vertrag.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('service_tickets', function (Blueprint $table): void {
            $table->string('kind', 20)->default('incident')->after('queue_id');
            $table->nullableMorphs('requester', 'svt_requester_idx');
            $table->unsignedTinyInteger('impact')->nullable();   // 1–3 (TicketSeverity)
            $table->unsignedTinyInteger('urgency')->nullable();  // 1–3 (TicketSeverity)
            $table->string('wait_reason', 300)->nullable();
            $table->timestamp('wait_until')->nullable();
            $table->foreignId('wait_owner_id')->nullable()
                ->constrained('users', indexName: 'svt_wait_owner_fk')
                ->nullOnDelete();
            $table->unsignedTinyInteger('escalation_level')->default(0);
            $table->string('confidentiality', 12)->default('normal'); // normal|restricted
            $table->text('resolution_summary')->nullable();
            $table->string('close_code', 20)->nullable(); // TicketCloseCode
            $table->json('sla_snapshot')->nullable();      // Vertragsstand beim Anlegen (P3)
        });

        Schema::create('service_ticket_watchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'svtw_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('service_ticket_id')
                ->constrained('service_tickets', indexName: 'svtw_ticket_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users', indexName: 'svtw_user_fk')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['service_ticket_id', 'user_id'], 'svtw_ticket_user_unique');
        });

        // SLA-Uhr-Segmente: Pausen je Frist-Ziel, reproduzierbar (DoD 065).
        Schema::create('sla_clock_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'slcs_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('service_ticket_id')
                ->constrained('service_tickets', indexName: 'slcs_ticket_fk')
                ->cascadeOnDelete();
            $table->string('target', 12); // reaction|resolution
            $table->timestamp('paused_from');
            $table->timestamp('paused_to')->nullable(); // offen = Pause läuft
            $table->string('reason', 300);
            $table->timestamps();

            $table->index(['service_ticket_id', 'target'], 'slcs_ticket_target_idx');
        });

        Schema::table('sla_contracts', function (Blueprint $table): void {
            // Gründe, die die SLA-Uhr anhalten (z. B. waiting_customer) —
            // NUR deklarierte Gründe pausieren (Vorgabe 065 P1).
            $table->json('pause_rules')->nullable();
        });
    }

    public function down(): void {
        Schema::table('sla_contracts', function (Blueprint $table): void {
            $table->dropColumn('pause_rules');
        });
        Schema::dropIfExists('sla_clock_segments');
        Schema::dropIfExists('service_ticket_watchers');
        Schema::table('service_tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('wait_owner_id');
            $table->dropMorphs('requester', 'svt_requester_idx');
            $table->dropColumn([
                'kind', 'impact', 'urgency', 'wait_reason', 'wait_until',
                'escalation_level', 'confidentiality', 'resolution_summary',
                'close_code', 'sla_snapshot',
            ]);
        });
    }
};
