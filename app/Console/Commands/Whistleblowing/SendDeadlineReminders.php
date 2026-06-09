<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SendDeadlineReminders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Whistleblowing;

use App\Models\Whistleblowing\WhistleblowingCase;
use App\Notifications\Whistleblowing\WhistleblowingDeadlineNotification;
use App\Services\Whistleblowing\WhistleblowingDeadlineService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Notification};

/**
 * Benachrichtigt zugewiesene Bearbeiter ueber faellige Hinweisgeber-Fristen
 * (inhaltsarm, Abschnitt 15). Laeuft mindestens stuendlich.
 */
class SendDeadlineReminders extends Command {
    protected $signature = 'whistleblowing:deadlines';

    protected $description = 'Benachrichtigt Bearbeiter ueber faellige Hinweisgeber-Fristen (inhaltsarm).';

    public function handle(WhistleblowingDeadlineService $deadlines): int {
        $sent = 0;

        foreach ($deadlines->dueReminders() as $reminder) {
            /** @var WhistleblowingCase $case */
            $case = $reminder['case'];
            $kind = (string) $reminder['kind'];

            // Idempotenz (Abschnitt 15): pro Fall/Art/Tag nur einmal erinnern.
            $inserted = DB::table('whistleblowing_deadline_reminders')->insertOrIgnore([
                'case_id' => $case->getKey(),
                'kind' => $kind,
                'reminder_date' => Carbon::now()->toDateString(),
                'created_at' => Carbon::now(),
            ]);
            if ($inserted === 0) {
                continue; // heute bereits erinnert
            }

            $handlers = $case->handlers()->get();
            if ($handlers->isEmpty()) {
                continue;
            }

            $due = $kind === 'acknowledge' ? $case->acknowledgement_due_at : $case->feedback_due_at;

            Notification::send($handlers, new WhistleblowingDeadlineNotification(
                caseNumber: (string) $case->getAttribute('case_number'),
                priority: $case->priority->value,
                kind: $kind,
                dueAt: $due?->format('Y-m-d'),
                url: route('whistleblowing.internal.show', $case),
            ));

            $sent++;
        }

        $this->info("Hinweisgeber-Fristen: {$sent} Faelle benachrichtigt.");

        return self::SUCCESS;
    }
}
