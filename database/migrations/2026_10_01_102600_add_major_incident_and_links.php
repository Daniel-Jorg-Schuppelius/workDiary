<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_102600_add_major_incident_and_links.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 065, P5 (MVP-155): Major-Incident-Kennzeichnung am Ticket
 * (Lead/Stakeholder/Kommunikationsrhythmus), Workaround getrennt von der
 * Lösung, Ticketverknüpfungen als Morph-Links (Ticket↔Ticket UND
 * Ticket→ISMS-/Datenschutz-Objekt — nie Konvertierung, nur Verknüpfung).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('service_tickets', function (Blueprint $table): void {
            $table->boolean('is_major')->default(false);
            $table->foreignId('incident_lead_id')->nullable()
                ->constrained('users', indexName: 'svt_incident_lead_fk')
                ->nullOnDelete();
            $table->json('stakeholders')->nullable();
            $table->string('comm_rhythm', 100)->nullable();
            $table->text('workaround')->nullable();
        });

        Schema::create('service_ticket_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'svtl_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('service_ticket_id')
                ->constrained('service_tickets', indexName: 'svtl_ticket_fk')
                ->cascadeOnDelete();
            $table->morphs('linked', 'svtl_linked_idx'); // ServiceTicket|IsmsIncident|PrivacyCase|…
            $table->string('kind', 20); // related|duplicate|parent|security|privacy
            $table->timestamps();

            $table->unique(['service_ticket_id', 'linked_type', 'linked_id', 'kind'], 'svtl_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('service_ticket_links');
        Schema::table('service_tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('incident_lead_id');
            $table->dropColumn(['is_major', 'stakeholders', 'comm_rhythm', 'workaround']);
        });
    }
};
