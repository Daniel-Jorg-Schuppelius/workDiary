<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Models\Customer\CustomerDeadline;
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use App\Services\Customer\Deadlines\CustomerDeadlineRuleSets;
use App\Services\Notification\DeadlineScans\{AbstractDeadlineScan, DeadlineScanOptions};
use App\Services\Notification\NotificationDispatcher;
use App\Support\{Formats, OrganizationContext};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Fristen-Scan der Kundenfristen (Feature 197, MVP-1633): je zuständiger
 * Person EINE gebündelte Vorwarnung für alle Fristen, die in die Vorwarnzeit
 * ihres Regelsatzes rücken (Organisationseinstellung, Vorgabe 7 Tage, je
 * Regelsatz überschreibbar), und eine gebündelte Meldung für neu überfällige.
 * Jede Frist meldet je Stufe nur einmal (`warned_at`, `overdue_warned_at`;
 * Verlängerung und Neuberechnung setzen sie zurück). Die Vertretung erhält die
 * Meldung mit (Positivliste `NotificationEvent::forwardsToDeputy()`);
 * Überfälliges eskaliert nach der Regel der Organisation.
 */
final class CustomerDeadlineScan extends AbstractDeadlineScan {
    public function __construct(private readonly CustomerDeadlineRuleSets $ruleSets) {}

    public function key(): string {
        return 'customer_deadlines';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $today = CarbonImmutable::today();
        $sent = 0;
        $organizationIds = CustomerDeadline::query()->withoutGlobalScope(OrganizationScope::class)->open()->distinct()->pluck('organization_id');

        foreach (Organization::query()->whereIn('id', $organizationIds)->orderBy('id')->get() as $organization) {
            if (! $this->moduleActiveFor($organization)) {
                continue;
            }
            $sent += (int) OrganizationContext::run($organization, fn (): int => $this->scanOrganization($dispatcher, $organization, $today));
        }

        return $sent;
    }

    private function scanOrganization(NotificationDispatcher $dispatcher, Organization $organization, CarbonImmutable $today): int {
        $sent = 0;
        $base = CustomerDeadline::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->open()
            ->with(['customer:id,name', 'responsible']);

        $horizon = $today->addDays(CustomerDeadlineRuleSets::MAX_WARN_DAYS);
        $soon = (clone $base)->whereNull('warned_at')
            ->where('due_on', '>=', DateRange::day($today))
            ->where('due_on', '<', DateRange::dayAfter($horizon))
            ->orderBy('due_on')
            ->get()
            ->filter(fn (CustomerDeadline $deadline): bool => $deadline->due_on->toDateString() <= $today->addDays($this->ruleSets->warnDays($organization, $deadline->rule_set))->toDateString())
            ->values();
        foreach ($soon->groupBy(static fn (CustomerDeadline $deadline): string => (string) ($deadline->responsible_user_id ?? 0)) as $bundle) {
            $sent += $this->notifyBundle($dispatcher, NotificationEvent::CustomerDeadlineDueSoon, $bundle, 'warned_at');
        }

        $overdue = (clone $base)->whereNull('overdue_warned_at')
            ->where('due_on', '<', DateRange::day($today))
            ->orderBy('due_on')
            ->get();
        foreach ($overdue->groupBy(static fn (CustomerDeadline $deadline): string => (string) ($deadline->responsible_user_id ?? 0)) as $bundle) {
            $sent += $this->notifyBundle($dispatcher, NotificationEvent::CustomerDeadlineOverdue, $bundle, 'overdue_warned_at');
        }

        // Eskalation: offene, längst gemeldete Überfällige — die Leiter greift nur am Bezugssatz einer Meldung.
        $escalate = (clone $base)->whereNotNull('overdue_warned_at')->where('due_on', '<', DateRange::day($today))->get();
        foreach ($escalate as $deadline) {
            $sent += $dispatcher->escalateIfDue(NotificationEvent::CustomerDeadlineOverdue, $deadline, $this->payload(NotificationEvent::CustomerDeadlineOverdue, collect([$deadline])));
        }

        return $sent;
    }

    /** @param  Collection<int, CustomerDeadline>  $bundle */
    private function notifyBundle(NotificationDispatcher $dispatcher, NotificationEvent $event, Collection $bundle, string $flag): int {
        $first = $bundle->first();
        if (! $first instanceof CustomerDeadline) {
            return 0;
        }
        $responsible = $first->responsible;
        $sent = $dispatcher->notify($event, $first, $responsible instanceof User ? $responsible : null, $this->payload($event, $bundle), dedup: true);
        // Gemeldet ist gemeldet — auch ohne Empfänger, sonst käme dieselbe Frist täglich wieder.
        CustomerDeadline::query()->withoutGlobalScope(OrganizationScope::class)->whereKey($bundle->map(static fn (CustomerDeadline $deadline): int => (int) $deadline->id)->all())->update([$flag => now()]);

        return $sent;
    }

    /**
     * @param  Collection<int, CustomerDeadline>  $bundle
     * @return array{title: string, title_key: string, title_params: array<string, int>, message: string, message_key: string, message_params: array<string, string>, url: string, due_at: \Illuminate\Support\Carbon}
     */
    private function payload(NotificationEvent $event, Collection $bundle): array {
        /** @var CustomerDeadline $first */
        $first = $bundle->first();
        $group = $event === NotificationEvent::CustomerDeadlineOverdue ? 'overdue' : 'due_soon';
        $customers = $bundle->map(static fn (CustomerDeadline $deadline): string => (string) ($deadline->customer->name ?? ''))->filter()->unique()->take(5)->implode(', ');
        $titleParams = ['count' => $bundle->count()];
        $messageParams = ['date' => $first->due_on->format(Formats::date()), 'customers' => $customers];

        return [
            'title' => (string) __('customer_deadline.notification.' . $group . '.title', $titleParams),
            'title_key' => 'customer_deadline.notification.' . $group . '.title',
            'title_params' => $titleParams,
            'message' => (string) __('customer_deadline.notification.' . $group . '.message', $messageParams),
            'message_key' => 'customer_deadline.notification.' . $group . '.message',
            'message_params' => $messageParams,
            'url' => route('customer-deadlines.index', ['responsible' => 'mine', 'status' => $group === 'overdue' ? 'overdue' : 'open']),
            'due_at' => $first->due_on,
        ];
    }
}
