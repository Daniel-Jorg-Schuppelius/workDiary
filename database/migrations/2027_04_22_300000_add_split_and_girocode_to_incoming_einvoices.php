<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_22_300000_add_split_and_girocode_to_incoming_einvoices.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sammel-PDF und GiroCode im Rechnungseingang (Feature 208, MVP-1662/1663).
 *
 * - Teil einer Sammel-PDF: `source_document_id` ist das unveränderte Original
 *   im DMS, `source_page_from`/`source_page_to` der Seitenbereich, aus dem die
 *   Teil-PDF des Eingangs (`document_id`) abgeleitet ist; `source_sha256` hält
 *   den Hash des Originals, damit ein erneuter Upload der Sammel-PDF als
 *   Dublette auffällt, auch wenn der Sammel-Eingang selbst aufgeteilt ist.
 * - `split_proposal`: Stand der Grenzerkennung (läuft, Vorschlag, eine
 *   Rechnung, abgelehnt) mit den vorgeschlagenen Startseiten.
 * - `girocode`: gelesener GiroCode samt IBAN — at-rest verschlüsselt wie alle
 *   IBAN-Spalten; `girocode_confirmed_*` hält die auditierte Bestätigung einer
 *   abweichenden IBAN fest (gleiche Form wie `creditor_iban_confirmed_*`).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->foreignId('source_document_id')->nullable()
                ->constrained('documents', indexName: 'ine_source_document_fk')->nullOnDelete();
            $table->unsignedSmallInteger('source_page_from')->nullable();
            $table->unsignedSmallInteger('source_page_to')->nullable();
            $table->char('source_sha256', 64)->nullable();
            $table->json('split_proposal')->nullable();
            $table->text('girocode')->nullable();
            $table->dateTime('girocode_confirmed_at')->nullable();
            $table->foreignId('girocode_confirmed_by')->nullable()
                ->constrained('users', indexName: 'ine_girocode_confirmed_by_fk')->nullOnDelete();

            $table->index(['organization_id', 'source_sha256'], 'ine_org_source_sha_idx');
        });
    }

    public function down(): void {
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->dropIndex('ine_org_source_sha_idx');
            $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';
            $table->dropForeign($sqlite ? ['source_document_id'] : 'ine_source_document_fk');
            $table->dropForeign($sqlite ? ['girocode_confirmed_by'] : 'ine_girocode_confirmed_by_fk');
            $table->dropColumn([
                'source_document_id', 'source_page_from', 'source_page_to', 'source_sha256', 'split_proposal',
                'girocode', 'girocode_confirmed_at', 'girocode_confirmed_by',
            ]);
        });
    }
};
