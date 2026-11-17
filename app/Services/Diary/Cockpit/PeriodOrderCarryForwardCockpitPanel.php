<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeriodOrderCarryForwardCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary\Cockpit;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\Diary\Status;
use App\Enums\User\Permission;
use App\Models\Classification\EntryType;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Services\CarryForward\{CarryForwardCockpitList, CarryForwardNotes};
use App\Services\Classification\ClassificationResolver;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Support\EntityUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Offene Merker an Aufträgen mit Periode (Feature 201, MVP-1646) im Cockpit
 * des Kunden: Reihe Kunde × Eintragsart × Produktgruppe
 * ({@see DiaryEntry::carryForwardSeries()}). Die Reihen kommen aus den offenen
 * Merkern selbst (Präfix `order:<kunde>:`), Bezeichnung aus Eintragsart und
 * Produktgruppe, Ziel ist der jüngste Auftrag der Reihe. Sichtbar wie die
 * wiederkehrenden Aufträge nur mit Einsicht in alle Aufträge.
 */
final class PeriodOrderCarryForwardCockpitPanel implements CockpitPanel {
    public function __construct(
        private readonly CarryForwardNotes $notes,
        private readonly CarryForwardCockpitList $list,
        private readonly ClassificationResolver $classifications,
    ) {}

    public function order(): int {
        return 23;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Customer;
    }

    public function render(Model $subject, User $viewer): ?View {
        if (! $viewer->can(Permission::DiaryViewAny->value)) {
            return null;
        }
        $organizationId = (int) $subject->getAttribute('organization_id');
        $prefix = DiaryEntry::carryForwardOrderPrefix((int) $subject->getKey());
        $keys = $this->notes->openSeriesStartingWith($organizationId, $prefix);
        if ($keys === []) {
            return null;
        }

        /** @var array<string, array{entry_type_id: int, product_group: string}> $parsed */
        $parsed = [];
        foreach ($keys as $key) {
            $parts = explode(':', substr($key, strlen($prefix)), 2);
            if (count($parts) === 2 && ctype_digit($parts[0])) {
                $parsed[$key] = ['entry_type_id' => (int) $parts[0], 'product_group' => $parts[1]];
            }
        }
        $entryTypes = EntryType::query()->whereIn('id', array_column($parsed, 'entry_type_id'))->pluck('label', 'id');

        $series = [];
        foreach ($parsed as $key => ['entry_type_id' => $entryTypeId, 'product_group' => $productGroup]) {
            $group = $productGroup !== '' ? $this->classifications->resolveCode($organizationId, ClassificationDomain::ProductGroup, $productGroup) : null;
            $latest = $this->latestOrder((int) $subject->getKey(), $entryTypeId, $productGroup);
            $series[$key] = [
                'label' => implode(' · ', array_filter([
                    (string) ($entryTypes[$entryTypeId] ?? ''),
                    $group !== null ? $group->display_label : $productGroup,
                ], static fn (string $part): bool => $part !== '')),
                'url' => $latest !== null ? EntityUrl::for($latest) : null,
            ];
        }

        return $this->list->render($organizationId, $series, 'cockpit-carry-forward-period-orders', (string) __('carry_forward.cockpit.period_orders'));
    }

    /** Jüngster nicht stornierte Auftrag der Reihe — dort erscheinen die Merker ab ihrer Zielperiode. */
    private function latestOrder(int $customerId, int $entryTypeId, string $productGroup): ?DiaryEntry {
        return DiaryEntry::query()
            ->where('customer_id', $customerId)
            ->where('entry_type_id', $entryTypeId)
            ->whereNotNull('period_key')
            ->where('status', '!=', Status::Cancelled->value)
            ->when(
                $productGroup !== '',
                static fn (Builder $q) => $q->whereHas('classifications', static fn (Builder $c) => $c->where('domain', ClassificationDomain::ProductGroup->value)->where('code', $productGroup)),
                static fn (Builder $q) => $q->whereDoesntHave('classifications', static fn (Builder $c) => $c->where('domain', ClassificationDomain::ProductGroup->value)),
            )
            ->latest('id')
            ->first();
    }
}
