<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InviteSurveyOnOrderCompleted.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Survey;

use App\Events\Diary\OrderCompleted;
use App\Listeners\ModuleListener;
use App\Models\Diary\DiaryEntry;
use App\Services\Survey\SurveyService;

/** Nach Auftragsabschluss lädt die Organisation den Kunden zur Umfrage ein (MVP-1293). */
final class InviteSurveyOnOrderCompleted extends ModuleListener {
    public function __construct(private readonly SurveyService $surveys) {}

    protected function module(): string {
        return 'survey';
    }

    public function handle(OrderCompleted $event): void {
        if (! $this->shouldHandle($event->organizationId)) {
            return;
        }
        $entry = DiaryEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $event->organizationId)
            ->with('customer')
            ->find($event->diaryEntryId);
        $customer = $entry?->customer;
        if ($customer === null) {
            return;
        }

        $this->surveys->inviteByTrigger($event->organizationId, 'trigger_on_order_completion', (string) $customer->email, $customer, 'order');
    }
}
