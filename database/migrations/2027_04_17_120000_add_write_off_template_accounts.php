<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_120000_add_write_off_template_accounts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Nachzug zu MVP-1555: Das Vorgabekonto der Ausbuchung (Forderungsverluste)
 * fehlte in beiden Kontenplan-Vorlagen. Organisationen, deren Kontenplan aus
 * einer Vorlage stammt (erkennbar am Skontokonto desselben Rahmens), bekommen
 * es nachgetragen. Additiv und idempotent: vorhandene Nummern bleiben.
 */
return new class extends Migration {
    /** @var list<array{marker: string, number: string}> Skontokonto des Rahmens ⇒ Ausbuchungskonto */
    private const array CHARTS = [
        ['marker' => '8736', 'number' => '2400'],
        ['marker' => '4736', 'number' => '6930'],
    ];

    private const string NAME = 'Forderungsverluste (übliche Höhe)';

    public function up(): void {
        if (! Schema::hasTable('accounting_accounts')) {
            return;
        }

        foreach (self::CHARTS as $chart) {
            $organizations = DB::table('accounting_accounts')
                ->where('number', $chart['marker'])
                ->whereNotIn('organization_id', DB::table('accounting_accounts')->where('number', $chart['number'])->select('organization_id'))
                ->pluck('organization_id');

            foreach ($organizations as $organizationId) {
                DB::table('accounting_accounts')->insert([
                    'organization_id' => $organizationId,
                    'number' => $chart['number'],
                    'name' => self::NAME,
                    'type' => 'expense',
                    'normal_balance' => 'debit',
                    'euer_category' => 'expense',
                    'deductible_percent' => '100.00',
                    'datev_account' => $chart['number'],
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
