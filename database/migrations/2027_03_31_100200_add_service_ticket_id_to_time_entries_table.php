<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_100200_add_service_ticket_id_to_time_entries_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zeiten am Ticket (Feature 065, MVP-1259): Summe am Ticket und Ticketbezug
 * in der Abrechnung. Ein gelöschtes Ticket lässt die Zeit stehen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->foreignId('service_ticket_id')->nullable()
                ->constrained('service_tickets', indexName: 'te_service_ticket_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropForeign('te_service_ticket_fk');
            $table->dropColumn('service_ticket_id');
        });
    }
};
