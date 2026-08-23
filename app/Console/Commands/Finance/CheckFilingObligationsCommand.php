<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CheckFilingObligationsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Finance;

use App\Enums\Finance\AccountingSovereignty;
use App\Enums\Notification\NotificationEvent;
use App\Models\Accounting\AccountingFilingObligation;
use App\Models\{Organization, User};
use App\Services\Accounting\AccountingSovereigntyResolver;
use App\Services\Accounting\Filing\FilingObligationService;
use App\Services\Notification\NotificationDispatcher;
use App\Support\Formats;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Steuertermine abgleichen und an bevorstehende Fristen erinnern
 * (Feature 125, MVP-686).
 *
 * Der Lauf erinnert, er meldet nichts: Abgegeben wird bei ELSTER bzw. über die
 * Steuerberatung. Erinnert wird einmal je Pflicht — `notified_at` verhindert,
 * dass dieselbe Frist täglich neu meldet.
 */
class CheckFilingObligationsCommand extends Command {
    protected $signature = 'accounting:check-filings {--days=7 : Vorlauf in Tagen} {--date= : Stichtag (Standard: heute)}';

    protected $description = 'Gleicht steuerliche Meldepflichten ab und erinnert an fällige Fristen (MVP-686)';

    public function handle(
        FilingObligationService $obligations,
        AccountingSovereigntyResolver $sovereignty,
        NotificationDispatcher $notifications,
    ): int {
        $asOf = CarbonImmutable::parse((string) ($this->option('date') ?? 'today'))->startOfDay();
        $horizon = $asOf->addDays(max(0, (int) $this->option('days')));

        $synced = 0;
        $notified = 0;

        foreach (Organization::query()->orderBy('id')->cursor() as $organization) {
            if ($sovereignty->at($organization, $asOf) !== AccountingSovereignty::Local) {
                continue;
            }

            $result = $obligations->syncYear($organization, $asOf->year);
            $synced += $result['created'];

            // Auch das Folgejahr: Die Sondervorauszahlung ist im Februar fällig
            // und muss vorher sichtbar sein.
            if ($asOf->month === 12) {
                $obligations->syncYear($organization, $asOf->year + 1);
            }

            $due = AccountingFilingObligation::query()
                ->where('organization_id', $organization->id)
                ->open()
                ->whereNull('notified_at')
                ->whereDate('due_on', '<=', $horizon->toDateString())
                ->orderBy('due_on')
                ->get();

            $recipient = $this->recipient($organization);

            foreach ($due as $obligation) {
                if ($recipient instanceof User) {
                    $notifications->notify(
                        NotificationEvent::AccountingFilingDue,
                        $obligation,
                        $recipient,
                        [
                            'title' => (string) __('accounting.filing.notification.title', ['kind' => $obligation->kind->label()]),
                            'title_key' => 'accounting.filing.notification.title',
                            'title_params' => ['kind' => $obligation->kind->label()],
                            'message' => (string) __('accounting.filing.notification.message', [
                                'period' => $obligation->period_key,
                                'due' => $obligation->due_on->format(Formats::date()),
                            ]),
                            'message_key' => 'accounting.filing.notification.message',
                            'message_params' => [
                                'period' => $obligation->period_key,
                                'due' => $obligation->due_on->format(Formats::date()),
                            ],
                            'url' => route('finance.accounting.filings.index'),
                        ],
                    );
                }

                $obligation->update(['notified_at' => now()]);
                $notified++;
            }
        }

        $this->info(sprintf('%d Pflichten angelegt, %d Erinnerungen versendet.', $synced, $notified));

        return self::SUCCESS;
    }

    /** Empfänger: die Buchhaltung der Organisation, ersatzweise die Eigentümerin. */
    private function recipient(Organization $organization): ?User {
        return User::query()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->first();
    }
}
