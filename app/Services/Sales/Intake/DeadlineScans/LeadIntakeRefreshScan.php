<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadIntakeRefreshScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Models\Platform\{Organization, User};
use App\Models\Sales\LeadIntake;
use App\Models\Scopes\OrganizationScope;
use App\Services\Notification\DeadlineScans\{AbstractDeadlineScan, DeadlineScanOptions};
use App\Services\Notification\NotificationDispatcher;
use App\Support\{Formats, OrganizationContext};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Aktualisierung der Aufnahmeakten (Feature 200, MVP-1643): je zuständiger
 * Person EINE gebündelte Meldung für die angenommenen Akten, deren
 * Aktualisierung (GwG: je Risikostufe, Vorgabe jährlich) in den nächsten
 * {@see WARN_DAYS} Tagen fällig wird oder überfällig ist. Zuständig ist die
 * verantwortliche Person des Leads, sonst wer entschieden hat. Jede Akte
 * meldet je Frist einmal (`refresh_warned_at`; eine neue Fassung setzt neu an).
 */
final class LeadIntakeRefreshScan extends AbstractDeadlineScan {
    public const int WARN_DAYS = 30;

    public function key(): string {
        return 'lead_intake_refresh';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $horizon = CarbonImmutable::today()->addDays(self::WARN_DAYS);
        $organizationIds = $this->due($horizon)->distinct()->pluck('organization_id');
        $sent = 0;
        foreach (Organization::query()->whereIn('id', $organizationIds)->orderBy('id')->get() as $organization) {
            if (! $this->moduleActiveFor($organization)) {
                continue;
            }
            $sent += (int) OrganizationContext::run($organization, fn (): int => $this->scanOrganization($dispatcher, $organization, $horizon));
        }

        return $sent;
    }

    private function scanOrganization(NotificationDispatcher $dispatcher, Organization $organization, CarbonImmutable $horizon): int {
        $intakes = $this->due($horizon)
            ->where('organization_id', $organization->id)
            ->with(['lead:id,company,contact_name,responsible_user_id', 'lead.responsible', 'decider'])
            ->orderBy('refresh_due_on')
            ->get();
        $sent = 0;
        foreach ($intakes->groupBy(static fn (LeadIntake $intake): string => (string) ($intake->lead->responsible_user_id ?? $intake->decider_user_id ?? 0)) as $bundle) {
            $sent += $this->notifyBundle($dispatcher, $bundle);
        }

        return $sent;
    }

    /** @param  Collection<int, LeadIntake>  $bundle */
    private function notifyBundle(NotificationDispatcher $dispatcher, Collection $bundle): int {
        $first = $bundle->first();
        if (! $first instanceof LeadIntake) {
            return 0;
        }
        $recipient = $first->lead->responsible ?? $first->decider;
        $names = $bundle->map(static fn (LeadIntake $intake): string => (string) $intake->lead?->displayName())->filter()->unique()->take(5)->implode(', ');
        $titleParams = ['count' => $bundle->count()];
        $messageParams = ['date' => $first->refresh_due_on?->format(Formats::date()) ?? '', 'names' => $names];
        $sent = $dispatcher->notify(NotificationEvent::LeadIntakeRefreshDue, $first, $recipient instanceof User ? $recipient : null, [
            'title' => (string) __('lead_intake.notification.refresh.title', $titleParams),
            'title_key' => 'lead_intake.notification.refresh.title',
            'title_params' => $titleParams,
            'message' => (string) __('lead_intake.notification.refresh.message', $messageParams),
            'message_key' => 'lead_intake.notification.refresh.message',
            'message_params' => $messageParams,
            'url' => route('leads.intakes.index', ['status' => 'refresh']),
            'due_at' => $first->refresh_due_on,
        ], dedup: true);
        // Gemeldet ist gemeldet — auch ohne Empfänger, sonst käme dieselbe Akte täglich wieder.
        LeadIntake::query()->withoutGlobalScope(OrganizationScope::class)
            ->whereKey($bundle->map(static fn (LeadIntake $intake): int => (int) $intake->id)->all())
            ->update(['refresh_warned_at' => now()]);

        return $sent;
    }

    /** @return \Illuminate\Database\Eloquent\Builder<LeadIntake> */
    private function due(CarbonImmutable $horizon): \Illuminate\Database\Eloquent\Builder {
        return LeadIntake::query()->withoutGlobalScope(OrganizationScope::class)
            ->whereNull('superseded_at')
            ->whereNull('refresh_warned_at')
            ->whereNotNull('refresh_due_on')
            ->where('refresh_due_on', '<', DateRange::dayAfter($horizon));
    }
}
