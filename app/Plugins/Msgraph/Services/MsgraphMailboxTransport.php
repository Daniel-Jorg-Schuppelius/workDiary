<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphMailboxTransport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Services;

use App\Models\Platform\Organization;
use App\Plugins\Msgraph\Models\MsgraphMailConnection;
use App\Plugins\Msgraph\MsgraphPlugin;
use App\Services\Mail\Contracts\MailboxTransport;
use App\Services\Mail\MailboxGateway;

/** Graph-Postfächer (Feature 102) nutzen die Graph-Mail-Verbindung der Organisation. */
final class MsgraphMailboxTransport implements MailboxTransport {
    public function key(): string {
        return MsgraphPlugin::MAIL_TRANSPORT;
    }

    public function label(): string {
        return (string) __('msgraph::mail.transport.msgraph');
    }

    public function hint(): string {
        return (string) __('msgraph::mail.transport.msgraph_hint');
    }

    public function gateway(): MailboxGateway {
        return app(MsgraphMailboxGateway::class);
    }

    public function unavailableReason(Organization $organization): ?string {
        $mail = MsgraphMailConnection::query()->where('organization_id', $organization->id)->first();

        return $mail instanceof MsgraphMailConnection && $mail->isActive() ? null : (string) __('msgraph::mail.flash.msgraph_connection_required');
    }
}
