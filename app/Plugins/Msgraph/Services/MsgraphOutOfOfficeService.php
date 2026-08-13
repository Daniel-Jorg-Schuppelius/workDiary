<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphOutOfOfficeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Services;

use App\Models\{MsgraphConnection, Organization, Vacation};
use App\Plugins\Msgraph\Api\MsgraphCalendarClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Automatische Outlook-Abwesenheitsnotiz bei genehmigtem Urlaub
 * (Feature-103-Delta, Q1 „Export nach Exchange … inkl. automatischer
 * Abwesenheitsnotiz"). Opt-in je Organisation
 * (`settings.msgraph.oof_enabled`, Default AUS); braucht in der
 * Adminconsent-App die Application-Permission `MailboxSettings.ReadWrite`.
 * Fehler werden geloggt, nie in den Genehmigungsfluss propagiert.
 */
final class MsgraphOutOfOfficeService {
    public function applyForVacation(Vacation $vacation): bool {
        $org = Organization::query()->find($vacation->organization_id);
        if ($org === null || ! (bool) data_get($org->settings, 'msgraph.oof_enabled', false)) {
            return false;
        }

        $email = trim((string) ($vacation->user->email ?? ''));
        if ($email === '') {
            return false;
        }

        $connection = MsgraphConnection::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $org->getKey())
            ->first();
        if (! $connection instanceof MsgraphConnection || ! $connection->isActive()) {
            return false;
        }

        try {
            $client = new MsgraphCalendarClient($connection);
            $tz = \App\Support\Tz::current();
            $start = $vacation->start_date->copy()->startOfDay();
            $end = $vacation->end_date->copy()->endOfDay();
            $message = (string) __('Ich bin bis einschließlich :date abwesend und lese Ihre Nachricht danach.', [
                'date' => $vacation->end_date->format('d.m.Y'),
            ]);

            $ok = $client->setAutomaticReplies($email, [
                'status' => 'scheduled',
                'scheduledStartDateTime' => [
                    'dateTime' => $start->format('Y-m-d\TH:i:s'),
                    'timeZone' => $tz,
                ],
                'scheduledEndDateTime' => [
                    'dateTime' => $end->format('Y-m-d\TH:i:s'),
                    'timeZone' => $tz,
                ],
                'internalReplyMessage' => $message,
                'externalReplyMessage' => $message,
                'externalAudience' => 'all',
            ]);

            if (! $ok) {
                Log::info('msgraph.oof: Abwesenheitsnotiz konnte nicht gesetzt werden.', [
                    'organization_id' => (int) $org->getKey(),
                    'vacation_id' => (int) $vacation->getKey(),
                ]);
            }

            return $ok;
        } catch (Throwable $e) {
            Log::warning('msgraph.oof: Fehler beim Setzen der Abwesenheitsnotiz.', [
                'organization_id' => (int) $org->getKey(),
                'vacation_id' => (int) $vacation->getKey(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
