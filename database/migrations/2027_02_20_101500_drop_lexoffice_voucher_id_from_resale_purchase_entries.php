<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_20_101500_drop_lexoffice_voucher_id_from_resale_purchase_entries.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Feature 152 (Review 2026-09-11, Kleinigkeiten): die Altspalte
 * `lexoffice_voucher_id` der Einkaufszeilen geht — der Belegbezug liegt seit
 * `101200` im Morph `document_type/document_id`, den jede Quelle der
 * Belegregistry trägt. Zeilen, die nur noch die Altspalte füllen, werden
 * vorher ein letztes Mal auf den Morph übertragen.
 */
return new class extends Migration {
    public function up(): void {
        DB::table('resale_purchase_entries')
            ->whereNotNull('lexoffice_voucher_id')
            ->where(static fn($q) => $q->whereNull('document_type')->orWhereNull('document_id'))
            ->update([
                'document_type' => (new \App\Models\LexofficeVoucher)->getMorphClass(),
                'document_id' => DB::raw('lexoffice_voucher_id'),
            ]);

        Schema::table('resale_purchase_entries', function (Blueprint $t): void {
            // FK vor der Spalte lösen (MySQL); SQLite baut die Tabelle dafür um.
            $t->dropConstrainedForeignId('lexoffice_voucher_id');
        });
    }

    public function down(): void {
        Schema::table('resale_purchase_entries', function (Blueprint $t): void {
            $t->foreignId('lexoffice_voucher_id')->nullable()->after('source')->constrained('lexoffice_vouchers')->nullOnDelete();
        });

        DB::table('resale_purchase_entries')
            ->where('document_type', (new \App\Models\LexofficeVoucher)->getMorphClass())
            ->whereNotNull('document_id')
            ->update(['lexoffice_voucher_id' => DB::raw('document_id')]);
    }
};
