<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_102300_create_service_ticket_messages_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 065, P2 (MVP-152): Konversation am Ticket. `kind` ist eine
 * TYPFRAGE (public_reply/internal_note/system_event) — Sichtbarkeit und
 * Versandfähigkeit leiten sich daraus ab, nie aus einem Flag.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('service_ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'svtm_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('service_ticket_id')
                ->constrained('service_tickets', indexName: 'svtm_ticket_fk')
                ->cascadeOnDelete();
            $table->string('kind', 20); // public_reply|internal_note|system_event
            $table->nullableMorphs('author', 'svtm_author_idx'); // User|CustomerUser|null (System)
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->string('subject', 255)->nullable();
            $table->text('body'); // sanitisiert (Mail-Intake-Sanitizer)
            $table->string('channel', 12)->default('manual'); // portal|mail|api|manual|system
            $table->string('message_id', 255)->nullable();    // Mail-Threading
            $table->string('in_reply_to', 255)->nullable();
            $table->string('delivery_status', 20)->nullable(); // queued|sent|failed
            $table->timestamps();

            $table->index(['service_ticket_id', 'created_at'], 'svtm_ticket_time_idx');
            $table->index('message_id', 'svtm_message_id_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('service_ticket_messages');
    }
};
