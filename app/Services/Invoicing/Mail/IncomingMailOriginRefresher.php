<?php
/*
 * Created on   : Thu Nov 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingMailOriginRefresher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Mail;

use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Mail\EmailConnection;
use App\Services\Mail\MailboxGateway;
use CommonToolkit\Helper\Data\StringHelper;
use Throwable;

/**
 * Herkunfts-Mail eines Eingangs nachladen (MVP-1813): Eingänge von vor
 * MVP-1811/1812 tragen weder Mailtext noch Empfangszeit und Postfach. Die Mail
 * wird über ihre Message-ID erneut aus dem Postfach gelesen — zuerst in den
 * Rechnungspostfächern, dann in den übrigen aktiven Postfächern der
 * Organisation. Vorhandene Angaben bleiben, nur Fehlendes wird ergänzt.
 */
final class IncomingMailOriginRefresher {
    public const string REFRESHED = 'refreshed';

    public const string NOT_FOUND = 'not_found';

    public const string NOT_APPLICABLE = 'not_applicable';

    public const string FAILED = 'failed';

    public function __construct(private readonly MailboxGateway $gateway) {}

    /** Aus dem Postfach, mit Message-ID und noch ohne Mailtext. */
    public static function applies(IncomingEInvoice $incoming): bool {
        return $incoming->mail_body === null
            && StringHelper::trimToNull($incoming->source_reference) !== null
            && StringHelper::trimToNull($incoming->sender_email) !== null;
    }

    /** @return self::REFRESHED|self::NOT_FOUND|self::NOT_APPLICABLE|self::FAILED */
    public function refresh(IncomingEInvoice $incoming): string {
        if (! self::applies($incoming)) {
            return self::NOT_APPLICABLE;
        }
        $connections = EmailConnection::query()->withoutGlobalScopes()
            ->where('organization_id', $incoming->organization_id)->where('active', true)
            ->orderByDesc('einvoice_intake')->orderBy('id')->get();
        if ($connections->isEmpty()) {
            return self::NOT_APPLICABLE;
        }

        $failed = false;
        foreach ($connections as $connection) {
            try {
                $message = $this->gateway->find($connection, (string) $incoming->source_reference);
            } catch (Throwable) {
                $failed = true;

                continue;
            }
            if ($message === null) {
                continue;
            }
            $summary = (array) $incoming->summary;
            $found = [
                'mail_subject' => mb_substr(trim($message->subject), 0, 500),
                'mail_from_name' => mb_substr(trim($message->fromName), 0, 191),
                'mail_received_at' => $message->receivedAt->toIso8601String(),
                'mail_mailbox' => mb_substr((string) $connection->name, 0, 191),
            ];
            foreach ($found as $key => $value) {
                if ($value !== '' && StringHelper::trimToNull(is_string($summary[$key] ?? null) ? $summary[$key] : null) === null) {
                    $summary[$key] = $value;
                }
            }
            $incoming->forceFill([
                // Nie die leere Zeichenkette: ein verschlüsseltes Feld liest sie nicht zurück.
                'mail_body' => StringHelper::trimToNull(mb_substr($message->body, 0, 100000)),
                'summary' => $summary,
            ])->save();
            $incoming->audit('incoming_einvoice.mail_origin_refreshed', ['mailbox' => $connection->name]);

            return self::REFRESHED;
        }

        return $failed ? self::FAILED : self::NOT_FOUND;
    }
}
