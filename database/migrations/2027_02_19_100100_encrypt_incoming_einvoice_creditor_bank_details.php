<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_100100_encrypt_incoming_einvoice_creditor_bank_details.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Crypt, DB, Schema};

/**
 * Vollscan 2026-08-23 (E5): incoming_einvoices.creditor_iban/creditor_bic lagen
 * als einzige IBAN-Spalten im Klartext. Jetzt wie bank_accounts/sepa_mandates:
 * text-Spalte (Ciphertext > 40 Zeichen, MariaDB strict) + encrypted-Cast;
 * der Bestand wird einmalig umverschlüsselt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->text('creditor_iban')->nullable()->change();
            $table->text('creditor_bic')->nullable()->change();
        });

        DB::table('incoming_einvoices')
            ->select(['id', 'creditor_iban', 'creditor_bic'])
            ->where(fn($q) => $q->whereNotNull('creditor_iban')->orWhereNotNull('creditor_bic'))
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('incoming_einvoices')->where('id', $row->id)->update([
                        'creditor_iban' => $row->creditor_iban !== null && $row->creditor_iban !== '' ? Crypt::encryptString((string) $row->creditor_iban) : null,
                        'creditor_bic' => $row->creditor_bic !== null && $row->creditor_bic !== '' ? Crypt::encryptString((string) $row->creditor_bic) : null,
                    ]);
                }
            });
    }

    public function down(): void {
        DB::table('incoming_einvoices')
            ->select(['id', 'creditor_iban', 'creditor_bic'])
            ->where(fn($q) => $q->whereNotNull('creditor_iban')->orWhereNotNull('creditor_bic'))
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('incoming_einvoices')->where('id', $row->id)->update([
                        'creditor_iban' => $row->creditor_iban !== null ? Crypt::decryptString((string) $row->creditor_iban) : null,
                        'creditor_bic' => $row->creditor_bic !== null ? Crypt::decryptString((string) $row->creditor_bic) : null,
                    ]);
                }
            });

        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->string('creditor_iban', 40)->nullable()->change();
            $table->string('creditor_bic', 20)->nullable()->change();
        });
    }
};
