<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LedgerAccountsSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Gdpdu;

use App\Models\Accounting\AccountingAccount;
use App\Models\Organization;
use Carbon\CarbonInterface;

/**
 * Kontenplan der lokalen Buchhaltung (Feature 125, MVP-677). Bewusst
 * OHNE Zeitraumfilter: Ein Journal ohne seine Konten ist nicht lesbar.
 */
class LedgerAccountsSection extends AbstractGdpduSection {
    public function key(): string {
        return 'ledger_accounts';
    }

    public function definition(): array {
        return [
            'file' => 'kontenplan.csv',
            'name' => 'Kontenplan',
            'description' => 'Konten der lokalen Buchhaltung mit Art, Saldenrichtung und DATEV-Zuordnung.',
            'columns' => [
                ['name' => 'Konto', 'type' => 'alpha'],
                ['name' => 'Bezeichnung', 'type' => 'alpha'],
                ['name' => 'Kontoart', 'type' => 'alpha'],
                ['name' => 'Saldenrichtung', 'type' => 'alpha'],
                ['name' => 'Offene_Posten', 'type' => 'alpha'],
                ['name' => 'DATEV_Konto', 'type' => 'alpha'],
                ['name' => 'Aktiv', 'type' => 'alpha'],
            ],
        ];
    }

    public function rows(Organization $organization, CarbonInterface $from, CarbonInterface $to): iterable {
        foreach (AccountingAccount::query()
            ->where('organization_id', $organization->id)
            ->orderBy('number')->orderBy('id')
            ->lazy() as $account) {
            yield [
                $this->str($account->number),
                $this->str($account->name),
                $this->str($account->type->value),
                $this->str($account->normal_balance->value),
                $account->is_open_item ? 'Ja' : 'Nein',
                $this->str($account->datev_account),
                $account->is_active ? 'Ja' : 'Nein',
            ];
        }
    }
}
