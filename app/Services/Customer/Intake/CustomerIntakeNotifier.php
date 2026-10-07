<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerIntakeNotifier.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Intake;

use App\Enums\Notification\NotificationEvent;
use App\Mail\CustomerIntakeNoticeMail;
use App\Models\Customer\CustomerIntake;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Facades\{Log, Mail};

/**
 * Nachrichten rund um den Kundeneingang (MVP-1074/1075): Mail an den
 * einreichenden Portalzugang und Benachrichtigung des Betriebs. Ein
 * Mailfehler wird protokolliert und am Eingang sichtbar markiert — er
 * verändert nie den gespeicherten Eingang oder eine Entscheidung.
 */
class CustomerIntakeNotifier {
    public const SUBMITTED = 'submitted';
    public const QUESTION = 'question';
    public const QUOTE = 'quote';
    public const REJECTED = 'rejected';
    public const HANDED_OVER = 'handed_over';
    public const PRINT_APPROVAL = 'print_approval';

    public function __construct(private readonly NotificationDispatcher $notifications) {}

    /** @return bool false, wenn keine Adresse vorliegt oder der Versand scheiterte */
    public function mailCustomer(CustomerIntake $intake, string $notice): bool {
        $email = trim((string) $intake->submitter?->email);
        if ($email === '') {
            return false;
        }

        try {
            Mail::to($email)->send(new CustomerIntakeNoticeMail($intake, $notice));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Kundeneingang: Mail fehlgeschlagen.', [
                'customer_intake_id' => $intake->id,
                'notice' => $notice,
                'error' => $e->getMessage(),
            ]);
            $intake->forceFill(['mail_failed_at' => now()])->save();
            $intake->record('mail_failed', ['notice' => $notice, 'error' => class_basename($e)]);

            return false;
        }
    }

    /**
     * Betrieb benachrichtigen: neu eingereicht an die Leitung, Aktivität an
     * die zuständige Person (sonst Leitung).
     *
     * @param  array<string, string>  $params
     */
    public function notifyStaff(CustomerIntake $intake, NotificationEvent $event, string $messageKey, array $params = []): void {
        $titleKey = 'customer_intake.notification.' . ($event === NotificationEvent::CustomerIntakeSubmitted ? 'submitted_title' : 'activity_title');
        $titleParams = ['number' => $intake->number, 'customer' => (string) $intake->customer?->name];
        $messageKey = 'customer_intake.notification.' . $messageKey;
        $messageParams = ['subject' => $intake->subject, ...$params];

        $this->notifications->notify($event, $intake, $intake->assignee, [
            'title' => (string) __($titleKey, $titleParams),
            'title_key' => $titleKey,
            'title_params' => $titleParams,
            'message' => (string) __($messageKey, $messageParams),
            'message_key' => $messageKey,
            'message_params' => $messageParams,
            'url' => route('customer-intakes.show', $intake),
        ]);
    }
}
