<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_100200_create_datev_booking_events_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Append-only Ereignisprotokoll des DATEV-Buchungsexports (Feature 045,
 * „Datenschutz, Sicherheit und Aufbewahrung"): jede Statusänderung eines
 * Buchungsstapels wird revisionssicher über die SHA-256-Hash-Kette
 * protokolliert (config('audit.chains'), prüfbar via `audit:verify`).
 *
 * Wie BillingTransferEvent/PaymentReconciliationEvent ohne FK auf
 * batch/organization: die Kette muss scope-frei verifizierbar sein und Einträge
 * überdauern die Löschung (organization_id geht in den Hash ein, ein Cascade
 * würde die Kette zerreißen). Der payload enthält bewusst keine PII.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('datev_booking_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('datev_booking_batch_id')->nullable();
            $table->string('event', 64);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('prev_hash', 64)->nullable();
            $table->string('hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['datev_booking_batch_id', 'event'], 'dbe_batch_event_idx');
            $table->index('hash', 'dbe_hash_idx');
        });

        if (Schema::hasTable('audit_chain_heads')) {
            DB::table('audit_chain_heads')->insertOrIgnore([
                'chain' => 'datev_booking_events',
                'head_hash' => null,
                'height' => 0,
            ]);
        }
    }

    public function down(): void {
        if (Schema::hasTable('audit_chain_heads')) {
            DB::table('audit_chain_heads')->where('chain', 'datev_booking_events')->delete();
        }
        Schema::dropIfExists('datev_booking_events');
    }
};
