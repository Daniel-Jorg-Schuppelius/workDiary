<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StampOrganizationMailHeader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Mail;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSending;

/**
 * Stampt die Organisations-ID als internen Routing-Header auf ausgehende
 * Mails (Feature 102, A2 Mandantenfähigkeit): Der Mailer-Kontext (v. a. im
 * Queue-Worker) kennt keine Organisation — die Mailable-Daten aber schon,
 * denn Laravel legt die public-Properties der Mailable (Invoice, TimeEntry, …)
 * in `$event->data`. Das erste org-tragende Eloquent-Modell gewinnt.
 *
 * Greift NUR, wenn der msgraph-Transport Teil der Default-Mailer-Kette ist —
 * bei reinem SMTP-Betrieb bleibt die ausgehende Mail unangetastet (der Header
 * würde sonst mit ausgeliefert). Der {@see MsgraphMailTransport} entfernt den
 * Header vor dem Versand wieder.
 */
class StampOrganizationMailHeader {
    public function handle(MessageSending $event): void {
        if (! MsgraphMailTransport::inDefaultMailerChain()) {
            return;
        }

        $headers = $event->message->getHeaders();
        if ($headers->has(MsgraphMailTransport::HEADER_ORGANIZATION)) {
            return; // explizit gesetzter Header gewinnt
        }

        foreach ($event->data as $value) {
            if (! $value instanceof Model) {
                continue;
            }
            $orgId = $value->getAttribute('organization_id');
            if (is_numeric($orgId) && (int) $orgId > 0) {
                $headers->addTextHeader(MsgraphMailTransport::HEADER_ORGANIZATION, (string) (int) $orgId);

                return;
            }
        }
    }
}
