<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_24_300000_bind_iban_confirmations_and_protect_split_originals.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Support\Crypto\BlindIndex;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Crypt, DB, Schema};

/**
 * Sicherheitsprüfung Feature 208:
 *
 * - M4: Die Bestätigung einer abweichenden Rechnungs-IBAN galt bisher für
 *   jede spätere IBAN. `creditor_iban_confirmed_hash` hält den geschlüsselten
 *   Abdruck (Blind-Index) der bestätigten IBAN; vorhandene Bestätigungen
 *   behalten ihre Wirkung für die jetzt gespeicherte IBAN, jede spätere
 *   Änderung sperrt wieder. Ist die IBAN nicht lesbar, verfällt die
 *   Bestätigung (sicherer Ausgang).
 * - N5: Der Verweis eines Teils auf das Original einer Sammel-PDF wird beim
 *   harten Löschen des Originals nicht mehr still geleert — die Datenbank
 *   lehnt ab, solange Teile bestehen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->string('creditor_iban_confirmed_hash', 64)->nullable();
        });

        DB::table('incoming_einvoices')
            ->whereNotNull('creditor_iban_confirmed_at')
            ->whereNotNull('creditor_iban')
            ->orderBy('id')
            ->chunkById(200, static function ($rows): void {
                foreach ($rows as $row) {
                    try {
                        $iban = Crypt::decryptString((string) $row->creditor_iban);
                    } catch (DecryptException) {
                        continue;
                    }
                    DB::table('incoming_einvoices')->where('id', $row->id)->update(['creditor_iban_confirmed_hash' => BlindIndex::ofIban($iban)]);
                }
            });

        $this->replaceSourceDocumentForeignKey(restrict: true);
    }

    public function down(): void {
        $this->replaceSourceDocumentForeignKey(restrict: false);
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->dropColumn('creditor_iban_confirmed_hash');
        });
    }

    /** SQLite kennt Fremdschlüssel nur über die Spalte (Tabelle wird neu aufgebaut), MySQL/MariaDB über den Namen. */
    private function replaceSourceDocumentForeignKey(bool $restrict): void {
        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';
        Schema::table('incoming_einvoices', function (Blueprint $table) use ($sqlite): void {
            $table->dropForeign($sqlite ? ['source_document_id'] : 'ine_source_document_fk');
        });
        Schema::table('incoming_einvoices', function (Blueprint $table) use ($restrict): void {
            $foreign = $table->foreign('source_document_id', 'ine_source_document_fk')->references('id')->on('documents');
            if ($restrict) {
                $foreign->restrictOnDelete();
            } else {
                $foreign->nullOnDelete();
            }
        });
    }
};
