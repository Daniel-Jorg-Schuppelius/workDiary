<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_20_101200_add_document_morph_to_resale_purchase_entries.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Feature 152 (Review 2026-09-11, Einkauf): Belegbezug der Einkaufszeile als
 * Morph — Eingangsbelege kommen aus dem Lexoffice-Spiegel, aus lokalen
 * Ausgaben oder aus Eingangs-E-Rechnungen; der Kern kennt keinen Anbieter.
 * Bestehende Lexoffice-Zuteilungen werden auf den Morph übertragen;
 * `lexoffice_voucher_id` bleibt vorerst als Altspalte stehen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('resale_purchase_entries', function (Blueprint $t): void {
            $t->string('document_type', 120)->nullable()->after('source');
            $t->unsignedBigInteger('document_id')->nullable()->after('document_type');
            $t->index(['organization_id', 'document_type', 'document_id'], 'resale_purchase_doc_idx');
        });

        DB::table('resale_purchase_entries')
            ->whereNotNull('lexoffice_voucher_id')
            ->whereNull('document_type')
            ->update([
                'document_type' => (new \App\Models\LexofficeVoucher)->getMorphClass(),
                'document_id' => DB::raw('lexoffice_voucher_id'),
            ]);
    }

    public function down(): void {
        Schema::table('resale_purchase_entries', function (Blueprint $t): void {
            $t->dropIndex('resale_purchase_doc_idx');
            $t->dropColumn(['document_type', 'document_id']);
        });
    }
};
