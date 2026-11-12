<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_250100_add_reverse_charge_template_tax_codes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Nachzug zu MVP-1621/1622 für Kontenpläne aus der SKR03-/SKR04-Vorlage
 * (erkannt am Steuerkonto des Kennzeichens USt19): Konten und Kennzeichen der
 * Steuerschuld des Empfängers, Kennzeichen RC60, BU-Schlüssel, DATEV-
 * Automatikkonten und die Regeln für Vorsteuer 7 % und Erlöse ohne Steuer.
 * Additiv und idempotent: Vorhandene Konten, Kennzeichen, Schlüssel,
 * Automatik-Zuordnungen und Regeln bleiben.
 */
return new class extends Migration {
    /** @var array<string, string> Kennzeichen ⇒ BU-Schlüssel */
    private const array DATEV_KEYS = ['USt19' => '3', 'USt7' => '2', 'VSt19' => '9', 'VSt7' => '8', 'RC21' => '47', 'AUSF' => '1'];

    /** @var array<string, array<string, mixed>> Kontenrahmen ⇒ Steuerkonto von USt19, Konten, Kennzeichen, Automatik, Regeln */
    private const array CHARTS = [
        'skr03' => [
            'marker' => '1776',
            'accounts' => [
                ['number' => '1572', 'name' => 'Abziehbare Vorsteuer aus innergemeinschaftlichem Erwerb', 'type' => 'asset', 'euer' => 'input_tax'],
                ['number' => '1574', 'name' => 'Abziehbare Vorsteuer aus innergemeinschaftlichem Erwerb 19 %', 'type' => 'asset', 'euer' => 'input_tax'],
                ['number' => '1577', 'name' => 'Abziehbare Vorsteuer nach § 13b UStG 19 %', 'type' => 'asset', 'euer' => 'input_tax'],
                ['number' => '1772', 'name' => 'Umsatzsteuer aus innergemeinschaftlichem Erwerb', 'type' => 'liability', 'euer' => 'income_vat'],
                ['number' => '1774', 'name' => 'Umsatzsteuer aus innergemeinschaftlichem Erwerb 19 %', 'type' => 'liability', 'euer' => 'income_vat'],
                ['number' => '1787', 'name' => 'Umsatzsteuer nach § 13b UStG 19 %', 'type' => 'liability', 'euer' => 'income_vat'],
                ['number' => '8337', 'name' => 'Erlöse aus Leistungen, für die der Leistungsempfänger die Steuer nach § 13b UStG schuldet', 'type' => 'income', 'euer' => 'income'],
            ],
            'codes' => [
                ['code' => 'RC13BEU', 'tax' => '1787', 'input' => '1577', 'base' => '46', 'field' => '47', 'input_field' => '67', 'rate' => '19.00', 'case' => 'reverse_charge_eu', 'key' => '94', 'name' => '§ 13b Abs. 1 UStG 19 %: sonstige Leistung aus dem übrigen Gemeinschaftsgebiet'],
                ['code' => 'RC13B', 'tax' => '1787', 'input' => '1577', 'base' => '84', 'field' => '85', 'input_field' => '67', 'rate' => '19.00', 'case' => 'reverse_charge', 'key' => '94', 'name' => '§ 13b Abs. 2 UStG 19 %: Steuerschuld des Leistungsempfängers'],
                ['code' => 'IGE19', 'tax' => '1774', 'input' => '1574', 'base' => '89', 'field' => '89', 'input_field' => '61', 'rate' => '19.00', 'case' => 'intra_community_acquisition', 'key' => '19', 'name' => 'Innergemeinschaftlicher Erwerb 19 %'],
                ['code' => 'IGE7', 'tax' => '1772', 'input' => '1572', 'base' => '93', 'field' => '93', 'input_field' => '61', 'rate' => '7.00', 'case' => 'intra_community_acquisition', 'key' => '18', 'name' => 'Innergemeinschaftlicher Erwerb 7 %'],
            ],
            'automatic' => ['1711' => 'USt7', '1718' => 'USt19', '3731' => 'VSt7', '3736' => 'VSt19', '8100' => 'FREI', '8125' => 'IGL', '8300' => 'USt7', '8337' => 'RC60', '8400' => 'USt19', '8731' => 'USt7', '8736' => 'USt19'],
            'rules' => [
                ['kind' => 'incoming_invoice', 'role' => 'tax_input', 'account' => '1571', 'match' => ['tax_rate' => '7.00'], 'code' => 'VSt7'],
                ['kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '8125', 'match' => ['tax_code' => 'IGL'], 'code' => null],
                ['kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '8337', 'match' => ['tax_code' => 'RC60'], 'code' => null],
            ],
        ],
        'skr04' => [
            'marker' => '3806',
            'accounts' => [
                ['number' => '1402', 'name' => 'Abziehbare Vorsteuer aus innergemeinschaftlichem Erwerb', 'type' => 'asset', 'euer' => 'input_tax'],
                ['number' => '1404', 'name' => 'Abziehbare Vorsteuer aus innergemeinschaftlichem Erwerb 19 %', 'type' => 'asset', 'euer' => 'input_tax'],
                ['number' => '1407', 'name' => 'Abziehbare Vorsteuer nach § 13b UStG 19 %', 'type' => 'asset', 'euer' => 'input_tax'],
                ['number' => '3802', 'name' => 'Umsatzsteuer aus innergemeinschaftlichem Erwerb', 'type' => 'liability', 'euer' => 'income_vat'],
                ['number' => '3804', 'name' => 'Umsatzsteuer aus innergemeinschaftlichem Erwerb 19 %', 'type' => 'liability', 'euer' => 'income_vat'],
                ['number' => '3837', 'name' => 'Umsatzsteuer nach § 13b UStG 19 %', 'type' => 'liability', 'euer' => 'income_vat'],
                ['number' => '4337', 'name' => 'Erlöse aus Leistungen, für die der Leistungsempfänger die Steuer nach § 13b UStG schuldet', 'type' => 'income', 'euer' => 'income'],
            ],
            'codes' => [
                ['code' => 'RC13BEU', 'tax' => '3837', 'input' => '1407', 'base' => '46', 'field' => '47', 'input_field' => '67', 'rate' => '19.00', 'case' => 'reverse_charge_eu', 'key' => '94', 'name' => '§ 13b Abs. 1 UStG 19 %: sonstige Leistung aus dem übrigen Gemeinschaftsgebiet'],
                ['code' => 'RC13B', 'tax' => '3837', 'input' => '1407', 'base' => '84', 'field' => '85', 'input_field' => '67', 'rate' => '19.00', 'case' => 'reverse_charge', 'key' => '94', 'name' => '§ 13b Abs. 2 UStG 19 %: Steuerschuld des Leistungsempfängers'],
                ['code' => 'IGE19', 'tax' => '3804', 'input' => '1404', 'base' => '89', 'field' => '89', 'input_field' => '61', 'rate' => '19.00', 'case' => 'intra_community_acquisition', 'key' => '19', 'name' => 'Innergemeinschaftlicher Erwerb 19 %'],
                ['code' => 'IGE7', 'tax' => '3802', 'input' => '1402', 'base' => '93', 'field' => '93', 'input_field' => '61', 'rate' => '7.00', 'case' => 'intra_community_acquisition', 'key' => '18', 'name' => 'Innergemeinschaftlicher Erwerb 7 %'],
            ],
            'automatic' => ['3260' => 'USt7', '3272' => 'USt19', '4100' => 'FREI', '4125' => 'IGL', '4300' => 'USt7', '4337' => 'RC60', '4400' => 'USt19', '4731' => 'USt7', '4736' => 'USt19', '5400' => 'VSt19', '5731' => 'VSt7', '5736' => 'VSt19'],
            'rules' => [
                ['kind' => 'incoming_invoice', 'role' => 'tax_input', 'account' => '1401', 'match' => ['tax_rate' => '7.00'], 'code' => 'VSt7'],
                ['kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '4125', 'match' => ['tax_code' => 'IGL'], 'code' => null],
                ['kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '4337', 'match' => ['tax_code' => 'RC60'], 'code' => null],
            ],
        ],
    ];

    public function up(): void {
        if (! Schema::hasTable('accounting_tax_codes') || ! Schema::hasColumn('accounting_tax_codes', 'input_tax_account_id')) {
            return;
        }

        $templates = DB::table('accounting_tax_codes')
            ->join('accounting_accounts', 'accounting_accounts.id', '=', 'accounting_tax_codes.tax_account_id')
            ->where('accounting_tax_codes.code', 'USt19')
            ->selectRaw('accounting_tax_codes.organization_id AS organization_id, accounting_accounts.number AS marker, MIN(accounting_tax_codes.valid_from) AS valid_from')
            ->groupBy('accounting_tax_codes.organization_id', 'accounting_accounts.number')
            ->orderBy('accounting_tax_codes.organization_id')
            ->get();

        $done = [];
        foreach ($templates as $template) {
            $chart = collect(self::CHARTS)->first(fn (array $chart): bool => $chart['marker'] === (string) $template->marker);
            if ($chart === null || isset($done[(int) $template->organization_id])) {
                continue;
            }
            $done[(int) $template->organization_id] = true;
            $this->backfill((int) $template->organization_id, (string) $template->valid_from, $chart);
        }
    }

    /** @param array<string, mixed> $chart */
    private function backfill(int $organizationId, string $validFrom, array $chart): void {
        $now = now();

        foreach ($chart['accounts'] as $account) {
            $exists = DB::table('accounting_accounts')->where('organization_id', $organizationId)->where('number', $account['number'])->exists();
            if (! $exists) {
                DB::table('accounting_accounts')->insert([
                    'organization_id' => $organizationId,
                    'number' => $account['number'],
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'normal_balance' => in_array($account['type'], ['asset', 'expense'], true) ? 'debit' : 'credit',
                    'euer_category' => $account['euer'],
                    'deductible_percent' => '100.00',
                    'datev_account' => $account['number'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
        $accounts = DB::table('accounting_accounts')->where('organization_id', $organizationId)->pluck('id', 'number');
        $codes = DB::table('accounting_tax_codes')->where('organization_id', $organizationId);

        foreach ($chart['codes'] as $code) {
            $taken = (clone $codes)->where(fn ($query) => $query
                ->where('code', $code['code'])
                ->orWhere(fn ($same) => $same->where('direction', 'both')->where('tax_case', $code['case'])->where('rate', $code['rate'])))
                ->exists();
            if ($taken || ! isset($accounts[$code['tax']], $accounts[$code['input']])) {
                continue;
            }
            DB::table('accounting_tax_codes')->insert([
                'organization_id' => $organizationId,
                'code' => $code['code'],
                'name' => $code['name'],
                'direction' => 'both',
                'rate' => $code['rate'],
                'tax_case' => $code['case'],
                'tax_account_id' => $accounts[$code['tax']],
                'input_tax_account_id' => $accounts[$code['input']],
                'ustva_base_field' => $code['base'],
                'ustva_tax_field' => $code['field'],
                'ustva_input_tax_field' => $code['input_field'],
                'datev_tax_key' => $code['key'],
                'valid_from' => $validFrom,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Kennziffer 60: steuerpflichtiger Umsatz, für den der Empfänger die Steuer schuldet (§ 13b Abs. 5 UStG).
        if ((clone $codes)->where(fn ($query) => $query->where('code', 'RC60')->orWhere(fn ($ae) => $ae->where('tax_category', 'AE')->where('ustva_base_field', '60')))->doesntExist()) {
            DB::table('accounting_tax_codes')->insert([
                'organization_id' => $organizationId,
                'code' => 'RC60',
                'name' => 'Steuerpflichtiger Umsatz, Steuerschuld des Leistungsempfängers § 13b Abs. 5 UStG',
                'direction' => 'none',
                'rate' => '0.00',
                'tax_category' => 'AE',
                'ustva_base_field' => '60',
                'datev_tax_key' => '46',
                'valid_from' => $validFrom,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (self::DATEV_KEYS as $code => $key) {
            (clone $codes)->where('code', $code)->whereNull('datev_tax_key')->update(['datev_tax_key' => $key, 'updated_at' => $now]);
        }

        $codeIds = (clone $codes)->where('is_active', true)->orderBy('valid_from')->pluck('id', 'code');
        foreach ($chart['automatic'] as $number => $code) {
            if (! isset($codeIds[$code])) {
                continue;
            }
            // Nur Konten, die auf dasselbe DATEV-Konto zeigen — ein umgeschlüsseltes Konto ist kein Standard-Automatikkonto.
            DB::table('accounting_accounts')
                ->where('organization_id', $organizationId)
                ->where('number', (string) $number)
                ->whereNull('default_tax_code_id')
                ->where(fn ($query) => $query->whereNull('datev_account')->orWhere('datev_account', (string) $number))
                ->update(['default_tax_code_id' => $codeIds[$code], 'updated_at' => $now]);
        }

        foreach ($chart['rules'] as $rule) {
            if (! isset($accounts[$rule['account']]) || ($rule['code'] !== null && ! isset($codeIds[$rule['code']]))) {
                continue;
            }
            $exists = DB::table('accounting_posting_rules')
                ->where('organization_id', $organizationId)
                ->where('source_kind', $rule['kind'])
                ->where('role', $rule['role'])
                ->pluck('match_criteria')
                ->contains(fn (?string $criteria): bool => (json_decode((string) $criteria, true) ?: null) == $rule['match']);
            if ($exists) {
                continue;
            }
            DB::table('accounting_posting_rules')->insert([
                'organization_id' => $organizationId,
                'source_kind' => $rule['kind'],
                'role' => $rule['role'],
                'accounting_account_id' => $accounts[$rule['account']],
                'accounting_tax_code_id' => $rule['code'] !== null ? $codeIds[$rule['code']] : null,
                'match_criteria' => json_encode($rule['match']),
                'priority' => 100,
                'version' => 1,
                'valid_from' => $validFrom,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void {
        // Konten, Kennzeichen und Regeln können bebucht sein; sie bleiben.
    }
};
