<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_21_100000_add_diary_entry_id_to_service_tickets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verknüpft ein Service-Ticket optional mit dem auslösenden Auftrag/Tagebuch-
 * eintrag (Feature 010 → Rang 42). So erscheint der SLA-Status in der
 * Auftrags-Fallakte und die Ticket-Anlage kann aus dem Auftragsdetail
 * vorbefüllt werden. `nullOnDelete`: verschwindet der Eintrag, bleibt das
 * Ticket bestehen (nur die Verknüpfung entfällt).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('service_tickets', function (Blueprint $table): void {
            $table->foreignId('diary_entry_id')->nullable()
                ->constrained('diary_entries', indexName: 'svcticket_diary_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('service_tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('diary_entry_id');
        });
    }
};
