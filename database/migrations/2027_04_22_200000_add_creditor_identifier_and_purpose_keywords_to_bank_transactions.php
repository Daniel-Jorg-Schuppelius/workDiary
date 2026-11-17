<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_22_200000_add_creditor_identifier_and_purpose_keywords_to_bank_transactions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kontierungshilfen der eigenen Buchhaltung (Feature 207, MVP-1660).
 *
 * - `creditor_identifier`: Gläubiger-ID einer SEPA-Lastschrift (CRED+ bzw.
 *   CdtrSchmeId), aus CAMT und MT940 übernommen, sofern die Datei sie trägt.
 *   Sie bezeichnet den einziehenden Gläubiger, nicht eine Person, und liegt wie
 *   die Mandatsreferenz im Klartext — nur so lässt sie sich vergleichen.
 * - `purpose_keyword_hash`: geschlüsselter Abdruck der normalisierten
 *   Stichwörter des Verwendungszwecks. Der Zweck selbst bleibt verschlüsselt;
 *   die Stichwörter werden nie im Klartext gespeichert.
 *
 * Bestandsumsätze behalten NULL: Die Gläubiger-ID lässt sich nachträglich nicht
 * gewinnen, den Abdruck rechnet die Vorschlagssuche bei Bedarf im Speicher.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->string('creditor_identifier', 35)->nullable()->after('mandate_ref');
            $table->string('purpose_keyword_hash', 64)->nullable()->after('purpose');

            $table->index(['organization_id', 'creditor_identifier'], 'bank_tx_org_creditor_idx');
            $table->index(['organization_id', 'mandate_ref'], 'bank_tx_org_mandate_idx');
            $table->index(['organization_id', 'purpose_keyword_hash'], 'bank_tx_org_keywords_idx');
        });
    }

    public function down(): void {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->dropIndex('bank_tx_org_creditor_idx');
            $table->dropIndex('bank_tx_org_mandate_idx');
            $table->dropIndex('bank_tx_org_keywords_idx');
            $table->dropColumn(['creditor_identifier', 'purpose_keyword_hash']);
        });
    }
};
