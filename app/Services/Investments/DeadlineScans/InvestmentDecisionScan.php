<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentDecisionScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Models\Investments\InvestmentBudgetRequest;
use App\Services\Notification\NotificationDispatcher;

/**
 * Vollaudit 2026-07 (M31): Budget-Anträge, die länger als :dueDays Tage
 * in Freigabe hängen — Fristenschiene MVP-209.
 */
class InvestmentDecisionScan extends AbstractDeadlineScan {
    public function key(): string {
        return 'investments';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $dueDays = $options->dueDays;

        return $this->runScan($dispatcher, [
            'due' => [
                'query' => fn() => InvestmentBudgetRequest::query()
                    ->withoutGlobalScopes()
                    ->where('status', 'in_approval')
                    ->where('created_at', '<=', now()->subDays($dueDays)),
                'event' => NotificationEvent::InvestmentDecisionDue,
                'payload' => function (InvestmentBudgetRequest $request): array {
                    $case = $request->investmentCase()->withoutGlobalScopes()->firstOrFail();

                    return [
                        'title' => (string) __('notification.message.investment_decision_due_title', ['title' => (string) $case->title]),
                        'title_key' => 'notification.message.investment_decision_due_title',
                        'title_params' => ['title' => (string) $case->title],
                        'message' => null,
                        'url' => route('investments.show', $case),
                    ];
                },
            ],
        ]);
    }
}
