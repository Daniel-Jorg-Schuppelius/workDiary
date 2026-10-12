<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_19_100100_add_duplicate_review_to_incoming_einvoices.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Möglicher Doppel-Eingang sperrt die Übergabe (MVP-1145): normalisierte
 * Rechnungsnummer als Suchschlüssel, Verweis auf den früheren Eingang und die
 * Bestätigung „kein Doppel“. Der Bestand bekommt nur den Schlüssel; bereits
 * vorhandene Eingänge werden nicht nachträglich gesperrt.
 *
 * Treffer ohne Rechnungsnummer im Buchhaltungsziel (MVP-1146): Kandidat am
 * Übergabejournal und die Entscheidung „anderer Beleg – neu anlegen“.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasColumn('incoming_einvoices', 'invoice_number_key')) {
            Schema::table('incoming_einvoices', function (Blueprint $table): void {
                $table->string('invoice_number_key', 64)->nullable()->after('invoice_number');
                $table->foreignId('duplicate_of_id')->nullable()->after('invoice_number_key')
                    ->constrained('incoming_einvoices', indexName: 'ine_duplicate_of_fk')->nullOnDelete();
                $table->foreignId('duplicate_cleared_user_id')->nullable()->after('duplicate_of_id')
                    ->constrained('users', indexName: 'ine_duplicate_cleared_user_fk')->nullOnDelete();
                $table->timestamp('duplicate_cleared_at')->nullable()->after('duplicate_cleared_user_id');
                $table->index(['organization_id', 'invoice_number_key'], 'ine_org_number_key_idx');
            });
        }

        if (! Schema::hasColumn('incoming_einvoice_transfers', 'candidate_external_id')) {
            Schema::table('incoming_einvoice_transfers', function (Blueprint $table): void {
                $table->string('candidate_external_id', 100)->nullable()->after('external_number');
                $table->string('candidate_label', 255)->nullable()->after('candidate_external_id');
                $table->foreignId('candidate_dismissed_user_id')->nullable()->after('candidate_label')
                    ->constrained('users', indexName: 'ine_transfer_candidate_user_fk')->nullOnDelete();
                $table->timestamp('candidate_dismissed_at')->nullable()->after('candidate_dismissed_user_id');
            });
        }

        DB::table('incoming_einvoices')->whereNotNull('invoice_number')->whereNull('invoice_number_key')->orderBy('id')
            ->chunkById(500, static function ($rows): void {
                foreach ($rows as $row) {
                    $key = mb_strtoupper(StringHelper::removeWhitespace((string) $row->invoice_number, true));
                    DB::table('incoming_einvoices')->where('id', $row->id)->update(['invoice_number_key' => $key !== '' ? mb_substr($key, 0, 64) : null]);
                }
            });
    }

    public function down(): void {
        Schema::table('incoming_einvoice_transfers', function (Blueprint $table): void {
            $table->dropForeign('ine_transfer_candidate_user_fk');
            $table->dropColumn(['candidate_external_id', 'candidate_label', 'candidate_dismissed_user_id', 'candidate_dismissed_at']);
        });
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->dropForeign('ine_duplicate_of_fk');
            $table->dropForeign('ine_duplicate_cleared_user_fk');
            $table->dropIndex('ine_org_number_key_idx');
            $table->dropColumn(['invoice_number_key', 'duplicate_of_id', 'duplicate_cleared_user_id', 'duplicate_cleared_at']);
        });
    }
};
