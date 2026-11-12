<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_320300_add_zero_rated_revenue_template_accounts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Nachzug zu MVP-1623 für Kontenpläne aus der SKR03-/SKR04-Vorlage (erkannt am
 * Steuerkonto des Kennzeichens USt19): Erlöskonten für die Kennzeichen RC21
 * (Kz 21), AUSF (Kz 43) und NSTB (Kz 45) als DATEV-Automatikkonten und die
 * Erlösregeln je Kennzeichen. Additiv und idempotent: Vorhandene Konten,
 * Automatik-Zuordnungen und Regeln bleiben.
 */
return new class extends Migration {
    /** @var array<string, array{marker: string, accounts: list<array{number: string, name: string, code: string}>}> */
    private const array CHARTS = [
        'skr03' => [
            'marker' => '1776',
            'accounts' => [
                ['number' => '8120', 'name' => 'Steuerfreie Umsätze § 4 Nr. 1a UStG', 'code' => 'AUSF'],
                ['number' => '8336', 'name' => 'Erlöse aus im anderen EU-Land steuerpflichtigen sonstigen Leistungen, für die der Leistungsempfänger die Umsatzsteuer schuldet', 'code' => 'RC21'],
                ['number' => '8338', 'name' => 'Erlöse aus im Drittland steuerbaren Leistungen, im Inland nicht steuerbare Umsätze', 'code' => 'NSTB'],
            ],
        ],
        'skr04' => [
            'marker' => '3806',
            'accounts' => [
                ['number' => '4120', 'name' => 'Steuerfreie Umsätze § 4 Nr. 1a UStG', 'code' => 'AUSF'],
                ['number' => '4336', 'name' => 'Erlöse aus im anderen EU-Land steuerpflichtigen sonstigen Leistungen, für die der Leistungsempfänger die Umsatzsteuer schuldet', 'code' => 'RC21'],
                ['number' => '4338', 'name' => 'Erlöse aus im Drittland steuerbaren Leistungen, im Inland nicht steuerbare Umsätze', 'code' => 'NSTB'],
            ],
        ],
    ];

    public function up(): void {
        if (! Schema::hasTable('accounting_tax_codes') || ! Schema::hasTable('accounting_posting_rules')) {
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
            $this->backfill((int) $template->organization_id, (string) $template->valid_from, $chart['accounts']);
        }
    }

    /** @param list<array{number: string, name: string, code: string}> $accounts */
    private function backfill(int $organizationId, string $validFrom, array $accounts): void {
        $now = now();
        $codeIds = DB::table('accounting_tax_codes')
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('valid_from')
            ->pluck('id', 'code');

        foreach ($accounts as $account) {
            if (! isset($codeIds[$account['code']])) {
                continue;
            }
            $accountId = DB::table('accounting_accounts')->where('organization_id', $organizationId)->where('number', $account['number'])->value('id');
            if ($accountId === null) {
                $accountId = DB::table('accounting_accounts')->insertGetId([
                    'organization_id' => $organizationId,
                    'number' => $account['number'],
                    'name' => $account['name'],
                    'type' => 'income',
                    'normal_balance' => 'credit',
                    'euer_category' => 'income',
                    'deductible_percent' => '100.00',
                    'datev_account' => $account['number'],
                    'default_tax_code_id' => $codeIds[$account['code']],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                // Nur ein Konto, das auf dasselbe DATEV-Konto zeigt, ist das Standard-Automatikkonto.
                DB::table('accounting_accounts')
                    ->where('id', $accountId)
                    ->whereNull('default_tax_code_id')
                    ->where(fn ($query) => $query->whereNull('datev_account')->orWhere('datev_account', $account['number']))
                    ->update(['default_tax_code_id' => $codeIds[$account['code']], 'updated_at' => $now]);
            }

            $exists = DB::table('accounting_posting_rules')
                ->where('organization_id', $organizationId)
                ->where('source_kind', 'sales_invoice')
                ->where('role', 'revenue')
                ->pluck('match_criteria')
                ->contains(fn (?string $criteria): bool => (json_decode((string) $criteria, true) ?: null) == ['tax_code' => $account['code']]);
            if ($exists) {
                continue;
            }
            DB::table('accounting_posting_rules')->insert([
                'organization_id' => $organizationId,
                'source_kind' => 'sales_invoice',
                'role' => 'revenue',
                'accounting_account_id' => $accountId,
                'accounting_tax_code_id' => null,
                'match_criteria' => json_encode(['tax_code' => $account['code']]),
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
        // Konten und Regeln können bebucht sein; sie bleiben.
    }
};
