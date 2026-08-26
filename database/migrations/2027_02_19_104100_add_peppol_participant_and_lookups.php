<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_104100_add_peppol_participant_and_lookups.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peppol-Empfängerdaten und SMP-Zwischenspeicher (Feature 066, MVP-734).
 *
 *  - `customers.peppol_participant_id` — Teilnehmerkennung in der Form
 *    `<ICD>:<Kennung>` (z. B. `9930:DE123456789`), geprüft über
 *    `ParticipantId::tryParse()`. `customers.peppol_scheme` haelt das
 *    Identifier-Schema; in Peppol praktisch immer `iso6523-actorid-upis`,
 *    aber die Spalte macht die Annahme sichtbar statt sie zu verstecken.
 *  - `peppol_participant_lookups` — Ergebnis der SML/SMP-Aufloesung je
 *    Organisation und Teilnehmer. Die Aufloesung kostet eine DNS- und eine
 *    HTTP-Runde; sie gehoert NICHT in jeden Versand. Der Datensatz ist ein
 *    Zwischenspeicher mit Ablaufzeit (`checked_at` + TTL der Plugin-Config),
 *    keine Stammdatenwahrheit — er wird bei Bedarf verworfen und neu geholt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('peppol_participant_id', 64)->nullable()->after('buyer_reference');
            $table->string('peppol_scheme', 40)->nullable()->after('peppol_participant_id');
        });

        Schema::create('peppol_participant_lookups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            // Kanonische Kennung `<scheme>::<ICD>:<Kennung>`.
            $table->string('participant', 120);
            $table->boolean('registered')->default(false);
            $table->string('smp_base_url', 255)->nullable();
            // Im SMP registrierte Dokumenttyp-Kennungen (kanonisch).
            $table->json('document_types')->nullable();
            // Klartext-Begruendung bei fehlgeschlagener Aufloesung.
            $table->string('message', 255)->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->unique(['organization_id', 'participant'], 'peppol_lookup_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('peppol_participant_lookups');

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['peppol_participant_id', 'peppol_scheme']);
        });
    }
};
