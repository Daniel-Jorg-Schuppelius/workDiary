<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FinanceMovementsWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\{WidgetGroup, WidgetWidth};
use App\Models\Platform\{Organization, User};
use App\Services\Dashboard\Contracts\FinanceMovementSource;
use App\Services\Licensing\ModuleExtensions;
use App\Support\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Throwable;

/**
 * Finanzbewegungen aller angeschlossenen Quellen (Feature 181, MVP-1617):
 * offene Zuordnungen je Quelle, darunter die jüngsten Bewegungen der letzten
 * 30 Tage. Gerechnet und bearbeitet wird in der Liste der Quelle.
 */
class FinanceMovementsWidget extends Widget {
    public const int DAYS = 30;

    private const int LATEST = 6;

    public function key(): string {
        return 'finance-movements';
    }

    public function label(): string {
        return (string) __('dashboard.widget.finance_movements.label');
    }

    public function icon(): string {
        return 'account_balance_wallet';
    }

    public function defaultOrder(): int {
        return 133;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function defaultWidth(): WidgetWidth {
        return WidgetWidth::Full;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Finance;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.finance_movements.description');
    }

    public function availableFor(User $user): bool {
        return $this->sources($user) !== [];
    }

    public function render(User $user): View|string {
        $organization = OrganizationContext::current();
        if (! $organization instanceof Organization) {
            return '';
        }
        $since = CarbonImmutable::today()->subDays(self::DAYS);
        $rows = [];
        $latest = [];
        foreach ($this->sources($user) as $source) {
            try {
                $summary = $source->summary($organization, $since, self::LATEST);
            } catch (Throwable $e) {
                // Eine Quelle darf die Kachel nicht kippen; ihr Fehler wird gemeldet.
                report($e);

                continue;
            }
            $rows[] = ['source' => $source, 'summary' => $summary];
            foreach ($summary->latest as $movement) {
                $latest[] = ['source' => $source, 'movement' => $movement, 'url' => $summary->url];
            }
        }
        usort($latest, static fn (array $a, array $b): int => $b['movement']->bookedOn->getTimestamp() <=> $a['movement']->bookedOn->getTimestamp());

        return view('dashboard.widgets.finance-movements', [
            'rows' => $rows,
            'latest' => array_slice($latest, 0, self::LATEST),
            'days' => self::DAYS,
        ]);
    }

    /** @return list<FinanceMovementSource> */
    private function sources(User $user): array {
        $sources = [];
        // Zur Laufzeit aufgelöst: die Kachel lebt in der Registry länger als ein Request.
        foreach (app(ModuleExtensions::class)->activeFor(FinanceMovementSource::class, OrganizationContext::currentId() ?? $user->organization_id) as $class) {
            /** @var FinanceMovementSource $source */
            $source = app($class);
            if ($source->available($user)) {
                $sources[] = $source;
            }
        }

        return $sources;
    }
}
