<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_120300_add_zero_rated_tax_codes_and_reduced_discount_accounts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Nachzug zu MVP-1558: Steuerkennzeichen für Umsätze ohne Steuer (die
 * Buchungs-Inbox wählt sie über die Steuerkategorie) und die Skontokonten des
 * ermäßigten Satzes — nur in Kontenplänen aus der Vorlage. Additiv und
 * idempotent: Vorhandene Kennzeichen und Konten bleiben, eine schon gesetzte
 * Steuerkategorie wird nicht überschrieben.
 */
return new class extends Migration {
    /** @var array<string, string> Vorlagen-Kürzel ⇒ Steuerkategorie (EN 16931) */
    private const array CATEGORIES = ['IGL' => 'K', 'FREI' => 'E'];

    /** @var list<array{code: string, name: string, field: string, category: string}> */
    private const array CODES = [
        ['code' => 'RC21', 'name' => 'Nicht steuerbare sonstige Leistung § 18b UStG (Steuerschuld des Empfängers)', 'field' => '21', 'category' => 'AE'],
        ['code' => 'AUSF', 'name' => 'Steuerfreie Ausfuhrlieferung § 4 Nr. 1a UStG', 'field' => '43', 'category' => 'G'],
        ['code' => 'NSTB', 'name' => 'Nicht steuerbarer Umsatz (Leistungsort nicht im Inland)', 'field' => '45', 'category' => 'O'],
    ];

    /** @var list<array{marker: string, number: string, name: string}> Skontokonto 19 % ⇒ Konto 7 % */
    private const array ACCOUNTS = [
        ['marker' => '3736', 'number' => '3731', 'name' => 'Erhaltene Skonti 7 % Vorsteuer'],
        ['marker' => '5736', 'number' => '5731', 'name' => 'Erhaltene Skonti 7 % Vorsteuer'],
    ];

    public function up(): void {
        if (! Schema::hasTable('accounting_tax_codes') || ! Schema::hasTable('accounting_accounts')) {
            return;
        }

        foreach (self::CATEGORIES as $code => $category) {
            DB::table('accounting_tax_codes')
                ->where('code', $code)
                ->where('direction', 'none')
                ->whereNull('tax_category')
                ->update(['tax_category' => $category, 'updated_at' => now()]);
        }

        // Kontenpläne aus der Vorlage tragen das Kennzeichen USt19; dessen Stichtag gilt auch für die neuen.
        $templates = DB::table('accounting_tax_codes')
            ->where('code', 'USt19')
            ->selectRaw('organization_id, MIN(valid_from) AS valid_from')
            ->groupBy('organization_id')
            ->get();

        foreach ($templates as $template) {
            foreach (self::CODES as $definition) {
                $taken = DB::table('accounting_tax_codes')
                    ->where('organization_id', $template->organization_id)
                    ->where(fn ($query) => $query->where('code', $definition['code'])->orWhere('tax_category', $definition['category']))
                    ->exists();
                if ($taken) {
                    continue;
                }

                DB::table('accounting_tax_codes')->insert([
                    'organization_id' => $template->organization_id,
                    'code' => $definition['code'],
                    'name' => $definition['name'],
                    'direction' => 'none',
                    'rate' => '0.00',
                    'tax_category' => $definition['category'],
                    'ustva_base_field' => $definition['field'],
                    'valid_from' => $template->valid_from,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
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
                    'type' => 'expense',
                    'normal_balance' => 'debit',
                    'euer_category' => 'expense',
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
        // Kennzeichen und Konten können bebucht sein; sie bleiben.
    }
};
