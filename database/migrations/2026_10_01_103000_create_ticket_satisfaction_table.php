<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_103000_create_ticket_satisfaction_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 065, P10 (MVP-160): Zufriedenheits-Kurzbewertung nach Abschluss
 * — genau EINE Antwort je Ticket (Unique), nur über das Kundenportal.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('ticket_satisfaction', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'tsat_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('service_ticket_id')
                ->constrained('service_tickets', indexName: 'tsat_ticket_fk')
                ->cascadeOnDelete();
            $table->foreignId('portal_user_id')->nullable()
                ->constrained('users', indexName: 'tsat_user_fk')
                ->nullOnDelete();
            $table->unsignedTinyInteger('score'); // 1–5
            $table->string('comment', 500)->nullable();
            $table->timestamp('answered_at');
            $table->timestamps();

            $table->unique('service_ticket_id', 'tsat_ticket_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('ticket_satisfaction');
    }
};
