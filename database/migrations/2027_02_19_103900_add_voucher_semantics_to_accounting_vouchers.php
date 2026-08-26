<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_103900_add_voucher_semantics_to_accounting_vouchers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anbieterneutrale Belegsemantik (Feature 122, MVP-731 — Vollscan G18).
 *
 * Bis MVP-611 hing die Auswertung der Belegspiegelung an sevDesk-Rohwerten:
 * `voucher_type` = C/D und sevDesk-Statuszahlen. Sobald ein zweiter Anbieter
 * spiegelt, ist das falsch — easybill kennt Dokumenttypen (INVOICE, CREDIT,
 * STORNO), InvoicePlane Statuszahlen mit anderer Bedeutung, JTL wieder andere.
 * Die Rohwerte bleiben stehen (Nachweis, was das Fremdsystem gesagt hat); die
 * Auswertung läuft ab jetzt über drei normalisierte Spalten, die JEDER Puller
 * beim Spiegeln setzt.
 *
 * `source_changed_at` ist der Inkrement-Marker: der Zeitstempel der letzten
 * Änderung IM FREMDSYSTEM (easybill `edited_at`, InvoicePlane `invoice_date`
 * bzw. Änderungsspalte, JTL `modifiedDate`). Der nächste Lauf fragt ab da
 * weiter — nicht ab `synced_at`, denn das wäre die Zeit unserer Uhr.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('accounting_vouchers', function (Blueprint $table): void {
            // Normalisiert: eingehend/ausgehend/neutral (DocumentDirection).
            $table->string('direction', 16)->nullable()->after('voucher_type');
            // Normalisiert: invoice/credit_note/cancellation/expense/other (DocumentKind).
            $table->string('document_kind', 24)->nullable()->after('direction');
            // Normalisiert: draft/open/paid/cancelled (Zustand im Fremdsystem).
            $table->string('voucher_state', 16)->nullable()->after('voucher_status');
            // Stornobeleg (eigener Beleg, der einen anderen aufhebt).
            $table->boolean('is_cancellation')->default(false)->after('document_kind');
            // Externe ID des aufgehobenen Belegs, falls das System sie nennt.
            $table->string('cancels_external_id', 128)->nullable()->after('is_cancellation');
            // Änderungszeitpunkt im Fremdsystem (Inkrement-Marker des Pullers).
            $table->timestamp('source_changed_at')->nullable()->after('synced_at');

            $table->index(['organization_id', 'plugin_id', 'source_changed_at'], 'acc_voucher_org_plugin_chg_idx');
        });
    }

    public function down(): void {
        Schema::table('accounting_vouchers', function (Blueprint $table): void {
            $table->dropIndex('acc_voucher_org_plugin_chg_idx');
            $table->dropColumn([
                'direction', 'document_kind', 'voucher_state', 'is_cancellation',
                'cancels_external_id', 'source_changed_at',
            ]);
        });
    }
};
