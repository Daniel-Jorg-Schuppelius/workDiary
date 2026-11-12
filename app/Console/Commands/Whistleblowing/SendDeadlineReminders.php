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

use App\Console\Concerns\IteratesModuleOrganizations;
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
    use IteratesModuleOrganizations;

    protected $signature = 'whistleblowing:deadlines';

    protected $description = 'Benachrichtigt Bearbeiter ueber faellige Hinweisgeber-Fristen (inhaltsarm).';

    public function handle(WhistleblowingDeadlineService $deadlines): int {
        $sent = 0;
        $active = $this->moduleOrganizationIds();

        foreach ($deadlines->dueReminders() as $reminder) {
            /** @var WhistleblowingCase $case */
            $case = $reminder['case'];
            if (! in_array((int) $case->organization_id, $active, true)) {
                continue;
            }
            $kind = (string) $reminder['kind'];

            // Ohne Bearbeiter keine Zeile: Die Vorwarnung wäre sonst verbraucht, bevor sie jemand erhält.
            $handlers = $case->handlers()->get();
            if ($handlers->isEmpty()) {
                continue;
            }

            // Idempotenz (Abschnitt 15): Vorwarnstufen je Fall einmal, überfällige Fristen einmal am Tag.
            $sentBefore = in_array($kind, WhistleblowingDeadlineService::ONCE, true)
                && DB::table('whistleblowing_deadline_reminders')->where('case_id', $case->getKey())->where('kind', $kind)->exists();
            if ($sentBefore) {
                continue;
            }
            $inserted = DB::table('whistleblowing_deadline_reminders')->insertOrIgnore([
                'case_id' => $case->getKey(),
                'kind' => $kind,
                'reminder_date' => Carbon::now()->toDateString(),
                'created_at' => Carbon::now(),
            ]);
            if ($inserted === 0) {
                continue; // heute bereits erinnert
            }

            $due = $deadlines->dueAt($case, $kind);
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
