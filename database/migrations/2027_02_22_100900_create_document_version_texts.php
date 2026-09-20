<?php
/*
 * Created on   : Sat Sep 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_22_100900_create_document_version_texts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ausgelesener Text einer Dokumentversion für den Tätigkeitsindex (MVP-819).
 *
 * Eigene Tabelle statt Spalte an `document_versions`: Die Version ist
 * append-only (sie IST die Dokumenthistorie), der Text dagegen abgeleitetes,
 * jederzeit neu berechenbares Material. Eine Spalte dort hätte bedeutet, die
 * Unveränderlichkeit für einen Cache aufzuweichen.
 *
 * Die Extraktion läuft über php-pdf-toolkit und kostet bei Scans OCR-Zeit;
 * deshalb einmal je Version im Hintergrund, nicht bei jeder Indizierung.
 * `extracted_at` trennt „noch nicht versucht" von „versucht, kein Text"
 * (Bild ohne Schrift, nicht unterstütztes Format) — sonst liefe jeder Lauf
 * erneut in dieselbe OCR.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('document_version_texts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_version_id')
                ->unique('doc_version_texts_version_unique')
                ->constrained('document_versions')
                ->cascadeOnDelete();
            $table->longText('text')->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->string('failure_reason', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('document_version_texts');
    }
};
