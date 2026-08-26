<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CashDailyClosingsSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Gdpdu;

use App\Models\{CashDailyClosing, Organization};
use Carbon\CarbonInterface;

/**
 * Kassen-Tagesabschlüsse (Vollaudit 2026-07, M38): Kassensturz-Nachweis
 * mit Soll/Ist/Differenz + Kassenstammdaten je Prüfungszeitraum.
 */
class CashDailyClosingsSection extends AbstractGdpduSection {
    public function key(): string {
        return 'cash_daily_closings';
    }

    public function definition(): array {
        return [
            'file' => 'kassenabschluss.csv',
            'name' => 'Kassen-Tagesabschlüsse',
            'description' => 'Tagesabschlüsse mit Kassensturz (Soll/Ist/Differenz) je Kasse im Prüfungszeitraum, inkl. Kassenstammdaten (MVP-414).',
            'columns' => [
                ['name' => 'Kasse', 'type' => 'alpha'],
                ['name' => 'Waehrung', 'type' => 'alpha'],
                ['name' => 'Anfangsbestand', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Eroeffnet_am', 'type' => 'date'],
                ['name' => 'Abschlussdatum', 'type' => 'date'],
                ['name' => 'Sollbestand', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Istbestand', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Differenz', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Notiz', 'type' => 'alpha'],
                ['name' => 'Abgeschlossen_von', 'type' => 'alpha'],
                ['name' => 'Erfasst_am', 'type' => 'alpha'],
            ],
        ];
    }

    public function rows(Organization $organization, CarbonInterface $from, CarbonInterface $to): iterable {
        foreach (CashDailyClosing::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereBetween('closing_date', [$from->toDateString(), $to->toDateString()])
            ->with(['register:id,name,currency,opening_balance,opened_on', 'closedBy:id,name'])
            ->orderBy('cash_register_id')->orderBy('closing_date')->orderBy('id')
            ->lazy() as $closing) {
            yield [
                $this->str($closing->register?->name),
                $this->str($closing->register?->currency),
                $this->num($closing->register?->opening_balance, 2),
                $this->date($closing->register?->opened_on),
                $this->date($closing->closing_date),
                $this->num($closing->expected_balance?->toFloat(), 2),
                $this->num($closing->counted_balance?->toFloat(), 2),
                $this->num($closing->difference?->toFloat(), 2),
                $this->str($closing->note),
                $this->str($closing->closedBy?->name),
                $this->dateTime($closing->created_at),
            ];
        }
    }
}
