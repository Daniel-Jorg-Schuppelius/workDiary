<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_100100_create_datev_booking_sources_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quellnachweis je Buchungssatz (Feature 045): verknüpft einen Buchungsstapel
 * mit den abgebildeten Quellen (Invoice/Expense, morph) und hält den
 * Buchungs-Snapshot (Konto/Gegenkonto/Betrag/Steuerschlüssel). Der Unique-Index
 * über (source_type, source_id) bei finalisierten/exportierten Stapeln wird
 * fachlich im Service geprüft (Doppel-Übergabe-Schutz); zusätzlich ein Unique
 * je Batch+Quelle, damit dieselbe Quelle nicht doppelt in einem Stapel landet.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('datev_booking_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('datev_booking_batch_id')->constrained('datev_booking_batches')->cascadeOnDelete();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('debtor_account', 12);       // Debitorenkonto (Soll-Konto)
            $table->string('revenue_account', 12);       // Erlöskonto (Haben-Konto / Gegenkonto)
            $table->string('soll_haben', 1);             // S|H des Hauptkontos
            $table->decimal('amount', 14, 2);            // Bruttobetrag (Umsatz)
            $table->string('tax_key', 4)->nullable();    // DATEV BU-Schlüssel
            $table->string('document_ref', 36)->nullable();  // Belegfeld 1 (Rechnungsnummer)
            $table->timestamps();

            $table->index(['source_type', 'source_id'], 'dbs_source_idx');
            $table->unique(['datev_booking_batch_id', 'source_type', 'source_id'], 'dbs_batch_source_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('datev_booking_sources');
    }
};
