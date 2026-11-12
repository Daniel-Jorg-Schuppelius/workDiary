<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_120200_add_payable_settlement_template_accounts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Nachzug zu MVP-1555: Gegenkonten für Skonto und Ausbuchung an
 * Verbindlichkeiten (erhaltene Skonti, sonstige Erträge) in Kontenplänen aus
 * der Vorlage — erkennbar am Skontokonto desselben Rahmens. Additiv und
 * idempotent: vorhandene Nummern bleiben.
 */
return new class extends Migration {
    /** @var list<array{marker: string, number: string, name: string, type: string, normal_balance: string, euer_category: string}> */
    private const array ACCOUNTS = [
        ['marker' => '8736', 'number' => '3736', 'name' => 'Erhaltene Skonti 19 % Vorsteuer', 'type' => 'expense', 'normal_balance' => 'debit', 'euer_category' => 'expense'],
        ['marker' => '8736', 'number' => '2709', 'name' => 'Sonstige Erträge unregelmäßig', 'type' => 'income', 'normal_balance' => 'credit', 'euer_category' => 'income'],
        ['marker' => '4736', 'number' => '5736', 'name' => 'Erhaltene Skonti 19 % Vorsteuer', 'type' => 'expense', 'normal_balance' => 'debit', 'euer_category' => 'expense'],
        ['marker' => '4736', 'number' => '4839', 'name' => 'Sonstige Erträge unregelmäßig', 'type' => 'income', 'normal_balance' => 'credit', 'euer_category' => 'income'],
    ];

    public function up(): void {
        if (! Schema::hasTable('accounting_accounts')) {
            return;
        }

        foreach (self::ACCOUNTS as $account) {
            $organizations = DB::table('accounting_accounts')
                ->where('number', $account['marker'])
                ->whereNotIn('organization_id', DB::table('accounting_accounts')->where('number', $account['number'])->select('organization_id'))
                ->pluck('organization_id');

            foreach ($organizations as $organizationId) {
                DB::table('accounting_accounts')->insert([
                    'organization_id' => $organizationId,
                    'number' => $account['number'],
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'normal_balance' => $account['normal_balance'],
                    'euer_category' => $account['euer_category'],
                    'deductible_percent' => '100.00',
                    'datev_account' => $account['number'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void {
        // Ein angelegtes Konto kann bebucht sein; es bleibt.
    }
};
