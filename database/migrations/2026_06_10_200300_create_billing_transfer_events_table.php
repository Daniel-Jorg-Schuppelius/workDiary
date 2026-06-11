<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_10_200300_create_billing_transfer_events_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Append-only Event-Hash-Kette für Übergabenachweise (Feature 045, analog
 * whistleblowing_case_events): jede Statusänderung wird revisionssicher
 * protokolliert (GoBD). organization_id bewusst nullable OHNE FK — die Kette
 * (config('audit.chains')) muss via `audit:verify` scope-frei über alle Zeilen
 * verifizierbar sein und Einträge überdauern Org-/Transfer-Löschungen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('billing_transfer_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('billing_transfer_id')->nullable();
            $table->string('event', 64);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->json('payload')->nullable();                // minimierte Metadaten, keine PII
            $table->string('prev_hash', 64)->nullable();
            $table->string('hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['billing_transfer_id', 'event'], 'bte_transfer_event_idx');
            $table->index('hash', 'bte_hash_idx');
        });

        // Kettenkopf vorab registrieren. Defensive: audit_chain_heads entsteht
        // erst in 2026_08_13_000012 — auf frischen Datenbanken legt
        // HashChained::performInsert() den Kopf beim ersten Event selbst an.
        if (Schema::hasTable('audit_chain_heads')) {
            DB::table('audit_chain_heads')->insertOrIgnore([
                'chain' => 'billing_transfer_events',
                'head_hash' => null,
                'height' => 0,
            ]);
        }
    }

    public function down(): void {
        if (Schema::hasTable('audit_chain_heads')) {
            DB::table('audit_chain_heads')->where('chain', 'billing_transfer_events')->delete();
        }
        Schema::dropIfExists('billing_transfer_events');
    }
};
