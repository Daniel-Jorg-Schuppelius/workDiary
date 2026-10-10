<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_14_110100_add_counterparty_matching_to_incoming_einvoices.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Models\Contacts\ContactBankAccount;
use App\Support\Crypto\BlindIndex;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Gegenpartei-Abgleich im Rechnungseingang (Feature 163, MVP-1108):
 * Lieferant bzw. Kunde am Eingang, IBAN-Blindindex an den Kontakt-
 * Bankverbindungen und ausdrücklich angelegte Absenderregeln.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            // Gelöschte Partei: der Eingang wird wieder zuzuordnen, er verschwindet nicht.
            $table->foreignId('supplier_id')->nullable()->after('kind')->constrained('suppliers')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->after('supplier_id')->constrained('customers')->nullOnDelete();
            $table->string('match_kind', 16)->nullable()->after('customer_id');
            $table->foreignId('matched_user_id')->nullable()->after('match_kind')->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at')->nullable()->after('matched_user_id');

            $table->index(['organization_id', 'supplier_id'], 'incoming_einv_org_supplier_idx');
            $table->index(['organization_id', 'customer_id'], 'incoming_einv_org_customer_idx');
        });

        Schema::table('contact_bank_accounts', function (Blueprint $table): void {
            $table->string('iban_hash', 64)->nullable()->after('iban');
            $table->index(['organization_id', 'iban_hash'], 'contact_bank_acc_org_iban_hash_idx');
        });

        // Bestand nachziehen: die IBAN liegt verschlüsselt, der Index entsteht nur aus dem Klartext.
        ContactBankAccount::query()->withoutGlobalScopes()->whereNotNull('iban')->orderBy('id')
            ->chunkById(500, static function ($accounts): void {
                foreach ($accounts as $account) {
                    try {
                        $hash = BlindIndex::ofIban($account->iban);
                    } catch (DecryptException) {
                        continue;
                    }
                    DB::table('contact_bank_accounts')->where('id', $account->id)->update(['iban_hash' => $hash]);
                }
            });

        Schema::create('invoice_sender_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email', 191);
            $table->string('direction', 16);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'email', 'direction'], 'invoice_sender_rule_org_email_dir_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('invoice_sender_rules');
        Schema::table('contact_bank_accounts', function (Blueprint $table): void {
            $table->dropIndex('contact_bank_acc_org_iban_hash_idx');
            $table->dropColumn('iban_hash');
        });
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->dropIndex('incoming_einv_org_supplier_idx');
            $table->dropIndex('incoming_einv_org_customer_idx');
            $table->dropConstrainedForeignId('matched_user_id');
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn(['match_kind', 'matched_at']);
        });
    }
};
