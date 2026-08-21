<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_25_100000_create_guarantees_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bürgschaftsregister (Feature 114, MVP-603).
 *
 * `direction` trennt **gestellte** von **erhaltenen** Bürgschaften. Die
 * Unterscheidung ist der Kern: Bei einer gestellten Bürgschaft läuft die
 * Avalprovision zulasten des eigenen Hauses weiter, solange sie nicht
 * zurückgefordert wird; bei einer erhaltenen ist umgekehrt die eigene
 * Sicherheit weg, wenn sie unbemerkt abläuft. Beide in einen Topf zu werfen
 * hieße, den jeweils falschen Alarm zu bauen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('guarantees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // issued = wir haben sie gestellt | received = wir haben sie erhalten
            $table->string('direction', 16);
            // performance | warranty | advance_payment | defects
            $table->string('kind', 24);
            $table->string('reference', 64)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('EUR');
            $table->date('issued_on')->nullable();
            // Befristung; null = unbefristet (bei Gewährleistungsbürgschaften üblich)
            $table->date('expires_on')->nullable();
            // Bürge (Bank/Versicherer) und Gegenpartei jeweils als freier Name
            // ODER als Kontakt — nicht jede Bank ist im Stammdatenbestand.
            $table->string('issuer_name', 191)->nullable();
            $table->foreignId('issuer_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            // Abgelöster Sicherheitseinbehalt (MVP-602) — der fachliche Grund
            // für die Reihenfolge der Kette 602 → 603.
            $table->foreignId('invoice_retention_id')->nullable()->constrained('invoice_retentions')->nullOnDelete();
            // active | returned | drawn | expired
            $table->string('status', 16)->default('active');
            $table->date('returned_on')->nullable();
            $table->string('returned_note', 500)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'expires_on'], 'guarantees_org_status_exp_idx');
            $table->index(['organization_id', 'direction'], 'guarantees_org_direction_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('guarantees');
    }
};
