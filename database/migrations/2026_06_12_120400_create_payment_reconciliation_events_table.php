<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_12_120400_create_payment_reconciliation_events_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only Ereignisprotokoll des Zahlungsabgleichs (Feature 045,
 * „Datenschutz, Sicherheit und Aufbewahrung"): jede Zuordnungsaktion
 * (confirm/unmatch/ignore/unassignable) wird revisionssicher über die
 * Hash-Kette protokolliert (registriert in config('audit.chains'), prüfbar
 * via `audit:verify`).
 *
 * Wie BillingTransferEvent ohne FK auf transaction/organization: die Kette muss
 * scope-frei verifizierbar sein und Einträge überdauern die Löschung
 * (organization_id geht in den Hash ein, ein Cascade würde die Kette zerreißen).
 * Der payload enthält bewusst keine PII (keine IBANs/Namen/Zwecke im Klartext).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('payment_reconciliation_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('bank_transaction_id')->nullable();
            $table->string('event', 64);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('prev_hash', 64)->nullable();
            $table->string('hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['bank_transaction_id', 'event'], 'pre_tx_event_idx');
            $table->index('hash', 'pre_hash_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('payment_reconciliation_events');
    }
};
