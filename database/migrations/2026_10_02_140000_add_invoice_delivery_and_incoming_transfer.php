<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_02_140000_add_invoice_delivery_and_incoming_transfer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 066, MVP-168 (Restpaket): Zustellnachweis je Ausgangsrechnung
 * (invoice_dispatches — Kanal, Empfänger, Format, Dateihash; erneuter
 * Versand = weiterer Zustellversuch, nie neue Rechnung), idempotente
 * Übergabe eingehender E-Rechnungen an die führende Buchhaltung
 * (transferred_at/_by) und Kennzeichnung dedizierter
 * Rechnungs-Postfächer für den Mail-Eingangskanal (MVP-165).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('invoice_dispatches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations', indexName: 'invd_org_fk')
                ->cascadeOnDelete();
            $table->foreignId('invoice_id')
                ->constrained('invoices', indexName: 'invd_invoice_fk')
                ->cascadeOnDelete();
            $table->string('channel', 20); // email|download|peppol|storage
            $table->string('format', 30)->nullable(); // pdf|xrechnung_ubl|zugferd_pdf
            $table->string('status', 12)->default('queued'); // queued|sent|failed
            $table->string('recipient', 500)->nullable();
            $table->char('sha256', 64)->nullable();
            $table->json('meta')->nullable(); // technische Antwort/Message-ID
            $table->foreignId('created_by')->nullable()
                ->constrained('users', indexName: 'invd_created_by_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['invoice_id', 'created_at'], 'invd_invoice_created_idx');
        });

        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->timestamp('transferred_at')->nullable();
            $table->foreignId('transferred_by')->nullable()
                ->constrained('users', indexName: 'ine_transferred_by_fk')
                ->nullOnDelete();
        });

        Schema::table('email_connections', function (Blueprint $table): void {
            // Dediziertes Rechnungs-Postfach: Anhänge werden als E-Rechnung
            // geprüft und in den Prüfbereich übernommen (MVP-165, Mail-Kanal).
            $table->boolean('einvoice_intake')->default(false);
        });
    }

    public function down(): void {
        Schema::dropIfExists('invoice_dispatches');
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('transferred_by');
            $table->dropColumn('transferred_at');
        });
        Schema::table('email_connections', function (Blueprint $table): void {
            $table->dropColumn('einvoice_intake');
        });
    }
};
