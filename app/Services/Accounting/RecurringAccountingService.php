<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurringAccountingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\{RecurringRunStatus, RecurringTemplateKind, RecurringTemplateStatus};
use App\Enums\Notification\NotificationEvent;
use App\Models\Accounting\{AccountingRecurringRun, AccountingRecurringTemplate};
use App\Models\{Organization, User};
use App\Services\Notification\NotificationDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Wiederkehrende Belegerwartungen und Buchungsvorlagen (Feature 125, MVP-675)
 * — EINZIGE Schreibstelle für Vorlagen und ihre Läufe.
 *
 * Die beiden Grenzen des Pakets stehen hier und nirgends sonst:
 *
 *  - Eine **Belegerwartung** erzeugt niemals einen Beleg. Sie eröffnet einen
 *    Vorgang mit Fälligkeit und erwartetem Betrag; erfüllt wird er erst durch
 *    das Original. Ein fingierter Eingangsbeleg würde die Lücke verstecken,
 *    statt sie zu zeigen — und im Zweifel eine Vorsteuer begründen, für die es
 *    kein Dokument gibt.
 *  - Eine **Buchungsvorlage** erzeugt ausschließlich einen datierten
 *    Buchungsentwurf. Festgeschrieben wird von Hand über die Inbox.
 *
 * Idempotenz über `(template, period_key)`: Ein zweiter Lauf derselben Periode
 * findet den Vorgang vor.
 */
class RecurringAccountingService {
    public function __construct(
        private readonly JournalService $journal,
        private readonly NotificationDispatcher $notifications,
    ) {}

    /**
     * Fällige Vorlagen abarbeiten.
     *
     * @return array{created: int, blocked: int, skipped: int}
     */
    public function runDue(Organization $organization, CarbonImmutable $asOf, User $actor): array {
        $created = 0;
        $blocked = 0;
        $skipped = 0;

        $templates = AccountingRecurringTemplate::query()
            ->where('organization_id', $organization->id)
            ->runnable()
            ->whereNotNull('next_due_on')
            ->whereDate('next_due_on', '<=', $asOf->toDateString())
            ->orderBy('next_due_on')
            ->get();

        foreach ($templates as $template) {
            if (! $template->isDueOn($asOf)) {
                $skipped++;

                continue;
            }

            $run = $this->runOnce($template, $actor);
            if ($run === null) {
                $skipped++;

                continue;
            }

            $run->status === RecurringRunStatus::Blocked ? $blocked++ : $created++;
        }

        return ['created' => $created, 'blocked' => $blocked, 'skipped' => $skipped];
    }

    /**
     * Einen einzelnen Lauf ausführen — idempotent. Existiert der Vorgang für
     * die Periode bereits, wird er zurückgegeben und der Fälligkeitszeiger
     * trotzdem fortgeschrieben (sonst bliebe die Vorlage hängen).
     */
    public function runOnce(AccountingRecurringTemplate $template, User $actor): ?AccountingRecurringRun {
        $dueOn = $template->next_due_on !== null
            ? CarbonImmutable::parse($template->next_due_on)->startOfDay()
            : null;

        if ($dueOn === null) {
            return null;
        }

        $periodKey = $template->interval->periodKey($dueOn);

        return DB::transaction(function () use ($template, $actor, $dueOn, $periodKey): AccountingRecurringRun {
            $existing = AccountingRecurringRun::query()
                ->where('accounting_recurring_template_id', $template->id)
                ->where('period_key', $periodKey)
                ->first();

            if ($existing instanceof AccountingRecurringRun) {
                $this->advance($template, $dueOn);

                return $existing;
            }

            $run = AccountingRecurringRun::query()->create([
                'organization_id' => $template->organization_id,
                'accounting_recurring_template_id' => $template->id,
                'period_key' => $periodKey,
                'due_on' => $dueOn->toDateString(),
                'status' => RecurringRunStatus::Expected,
                'expected_amount' => $template->expected_amount?->getAmount(),
                'currency' => $template->currency,
            ]);

            if ($template->kind === RecurringTemplateKind::PostingTemplate) {
                $this->createDraft($template, $run, $actor, $dueOn);
            }

            $this->advance($template, $dueOn);

            return $run->refresh();
        });
    }

    /**
     * Eine Belegerwartung durch das Original erfüllen. Erst hier ist der
     * Vorgang zu Ende — nicht schon beim Erzeugen.
     */
    public function fulfill(AccountingRecurringRun $run, Model $document): AccountingRecurringRun {
        if (! $run->status->isOpen()) {
            throw ValidationException::withMessages([
                'run' => (string) __('accounting.recurring.error.already_closed'),
            ]);
        }

        $run->update([
            'status' => RecurringRunStatus::Fulfilled,
            'fulfilled_by_type' => $document::class,
            'fulfilled_by_id' => $document->getKey(),
            'fulfilled_at' => now(),
        ]);

        return $run->refresh();
    }

    /** Überfällige Vorgänge melden — je Vorgang höchstens einmal. */
    public function notifyOverdue(Organization $organization, CarbonImmutable $asOf): int {
        $runs = AccountingRecurringRun::query()
            ->where('organization_id', $organization->id)
            ->overdue($asOf)
            ->whereNull('notified_at')
            ->with('template')
            ->get();

        $sent = 0;
        foreach ($runs as $run) {
            $template = $run->template;
            if (! $template instanceof AccountingRecurringTemplate) {
                continue;
            }

            $this->notifications->notify(
                NotificationEvent::AccountingRecurringOverdue,
                $run,
                $template->responsible,
                [
                    'title' => (string) __('accounting.recurring.notification.title', ['name' => $template->name]),
                    'title_key' => 'accounting.recurring.notification.title',
                    'title_params' => ['name' => $template->name],
                    'message' => (string) __('accounting.recurring.notification.message', [
                        'due' => $run->due_on->format(\App\Support\Formats::date()),
                        'status' => $run->status->label(),
                    ]),
                    'message_key' => 'accounting.recurring.notification.message',
                    'message_params' => [
                        'due' => $run->due_on->format(\App\Support\Formats::date()),
                        'status' => $run->status->label(),
                    ],
                    'url' => route('finance.accounting.recurring.index'),
                ],
            );

            $run->update(['notified_at' => now()]);
            $sent++;
        }

        return $sent;
    }

    public function pause(AccountingRecurringTemplate $template): AccountingRecurringTemplate {
        $template->update(['status' => RecurringTemplateStatus::Paused]);

        return $template->refresh();
    }

    public function resume(AccountingRecurringTemplate $template): AccountingRecurringTemplate {
        $template->update([
            'status' => RecurringTemplateStatus::Active,
            // Zeiger nachziehen, damit ein pausierter Plan nicht rückwirkend
            // eine Kette von Vorgängen nachholt.
            'next_due_on' => $this->nextDueFrom($template, CarbonImmutable::now())->toDateString(),
        ]);

        return $template->refresh();
    }

    public function end(AccountingRecurringTemplate $template): AccountingRecurringTemplate {
        $template->update(['status' => RecurringTemplateStatus::Ended, 'next_due_on' => null]);

        return $template->refresh();
    }

    /**
     * Vorschau der nächsten Fälligkeiten.
     *
     * @return list<string>
     */
    public function preview(AccountingRecurringTemplate $template, int $count = 6): array {
        $dates = [];
        $cursor = $template->next_due_on !== null
            ? CarbonImmutable::parse($template->next_due_on)
            : $this->firstDue($template);

        for ($i = 0; $i < $count; $i++) {
            if ($template->ends_on !== null && $cursor->greaterThan(CarbonImmutable::parse($template->ends_on))) {
                break;
            }

            $dates[] = $cursor->toDateString();
            $cursor = $template->interval->next($cursor);
        }

        return $dates;
    }

    /** Erste Fälligkeit aus Start und Fälligkeitstag. */
    public function firstDue(AccountingRecurringTemplate $template): CarbonImmutable {
        $start = CarbonImmutable::parse($template->starts_on)->startOfDay();
        $due = $start->day(min($template->due_day, 28));

        return $due->lessThan($start) ? $template->interval->next($due) : $due;
    }

    /** Buchungsentwurf aus der Vorlage — niemals festgeschrieben. */
    private function createDraft(AccountingRecurringTemplate $template, AccountingRecurringRun $run, User $actor, CarbonImmutable $dueOn): void {
        $lines = $template->template_lines ?? [];
        if ($lines === []) {
            $run->update([
                'status' => RecurringRunStatus::Blocked,
                'blocked_reason' => (string) __('accounting.recurring.blocker.no_lines'),
            ]);

            return;
        }

        $organization = $template->organization;
        if (! $organization instanceof Organization) {
            $run->update([
                'status' => RecurringRunStatus::Blocked,
                'blocked_reason' => (string) __('accounting.ledger.error.entry_without_organization'),
            ]);

            return;
        }

        try {
            $entry = $this->journal->draft($organization, [
                'booked_on' => $dueOn,
                'memo' => $template->name,
                'source_key' => 'recurring:' . $template->id . ':' . $run->period_key,
                'snapshot' => ['recurring_template' => $template->id, 'period_key' => $run->period_key],
                'lines' => $lines,
            ], $actor);

            $run->update([
                'status' => RecurringRunStatus::DraftCreated,
                'accounting_entry_id' => $entry->id,
            ]);
        } catch (ValidationException $exception) {
            // Ein blockierter Lauf ist ein Befund, kein Abbruch: Der Vorgang
            // bleibt sichtbar, damit jemand die Ursache beheben kann.
            $run->update([
                'status' => RecurringRunStatus::Blocked,
                'blocked_reason' => implode(' ', array_map(
                    static fn (array $messages): string => implode(' ', $messages),
                    $exception->errors(),
                )),
            ]);
        }
    }

    private function advance(AccountingRecurringTemplate $template, CarbonImmutable $dueOn): void {
        $next = $template->interval->next($dueOn);

        $template->update([
            'next_due_on' => $template->ends_on !== null && $next->greaterThan(CarbonImmutable::parse($template->ends_on))
                ? null
                : $next->toDateString(),
        ]);
    }

    private function nextDueFrom(AccountingRecurringTemplate $template, CarbonImmutable $asOf): CarbonImmutable {
        $cursor = $this->firstDue($template);
        while ($cursor->lessThan($asOf->startOfDay())) {
            $cursor = $template->interval->next($cursor);
        }

        return $cursor;
    }
}
