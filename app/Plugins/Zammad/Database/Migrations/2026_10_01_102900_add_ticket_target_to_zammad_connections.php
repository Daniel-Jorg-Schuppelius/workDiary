<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_102900_add_ticket_target_to_zammad_connections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 065, P8 (MVP-158): Zielmodus des Zammad-Imports — 'task'
 * (Bestand, Zammad führt = external) oder 'service_ticket' (Import als
 * ServiceTicket in eine benannte Queue). Kein stiller Mischbetrieb:
 * Wechsel nur über die Preflight-Admin-Aktion.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('zammad_connections', function (Blueprint $table): void {
            $table->string('ticket_target', 20)->default('task'); // task|service_ticket
            $table->foreignId('service_queue_id')->nullable()
                ->constrained('service_queues', indexName: 'zmc_queue_fk')
                ->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('zammad_connections', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_queue_id');
            $table->dropColumn('ticket_target');
        });
    }
};
