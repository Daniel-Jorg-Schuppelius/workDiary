<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_03_100000_create_time_tracking_webhook_deliveries_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zustellprotokoll der Zeiterfassungs-Webhooks (Feature 124, MVP-613).
 *
 * Eine Tabelle für Toggl UND Clockify: Die Zeilen unterscheiden sich nur im
 * `plugin_id`, und die Dedup-Regel ist dieselbe. Der Eintrag entsteht VOR der
 * Verarbeitung — ein Replay endet damit idempotent.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('time_tracking_webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('plugin_id', 32);
            $table->string('delivery_id', 191);
            $table->string('event_name', 128)->nullable();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['plugin_id', 'delivery_id'], 'ttwdel_plugin_delivery_unique');
            $table->index(['plugin_id', 'received_at'], 'ttwdel_plugin_received_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('time_tracking_webhook_deliveries');
    }
};
