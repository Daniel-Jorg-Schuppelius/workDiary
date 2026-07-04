<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_03_140000_create_todoist_webhook_deliveries_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webhook-Deduplizierung für Todoist (Feature 055, MVP-115): jede Zustellung
 * wird VOR der Verarbeitung mit ihrer Delivery-ID persistiert — Replays enden
 * idempotent (Unique-Constraint). organization_id ist nullable, weil die
 * Org-Zuordnung erst nach der Signaturprüfung über `todoist_user_id` erfolgt
 * und auch unzuordenbare (aber korrekt signierte) Zustellungen protokolliert
 * werden. Kurze, explizite Index-Namen (MySQL-64-Zeichen-Limit).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('todoist_webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('delivery_id', 191);
            $table->string('event_name', 100)->nullable();
            $table->foreignId('organization_id')->nullable()->constrained('organizations', indexName: 'twdel_org_fk')->nullOnDelete();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique('delivery_id', 'twdel_delivery_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('todoist_webhook_deliveries');
    }
};
