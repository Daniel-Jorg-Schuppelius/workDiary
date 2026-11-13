<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_21_100000_create_attachment_texts_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gelesene Mail-Datei eines Anhangs (Feature 204, MVP-1652/1653): Suchtext
 * (Betreff, Absender, Text), die Zusammenfassung für die Ansicht „Mail“ (nur
 * Arrays und Skalare, JSON) und ein Verzeichnis mit den einmal extrahierten
 * Anhängen und der bereinigten Originaldarstellung. Gelesen wird einmal im
 * Hintergrund; Ansicht, Download und Übernahme zerlegen die Mail nie erneut.
 *
 * Zustand ohne eigene Statusspalte: `extracted_at` leer = wird gelesen (die
 * Zeile entsteht vor dem Lesen und verhindert ein erneutes Anstoßen),
 * `failure_reason` gesetzt = nicht lesbar. `parts_path` ist ein Dateizeiger
 * (Verzeichnis) und steht in der Löschliste der Organisation; die Zeile fällt
 * mit dem Anhang weg.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('attachment_texts', function (Blueprint $table): void {
            $table->id();
            $table->organizationFkNullable();
            $table->foreignId('attachment_id')
                ->unique('attachment_texts_attachment_unique')
                ->constrained('attachments')
                ->cascadeOnDelete();
            $table->longText('text')->nullable();
            $table->longText('summary')->nullable();
            $table->string('parts_disk', 32)->nullable();
            $table->string('parts_path', 255)->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->string('failure_reason', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('attachment_texts');
    }
};
