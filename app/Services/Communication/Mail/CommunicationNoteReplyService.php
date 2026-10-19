<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationNoteReplyService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Communication\Mail;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType};
use App\Events\Communication\CommunicationNoteReplied;
use App\Exceptions\CommunicationNoteNotReplyableException;
use App\Mail\CommunicationNoteReplyMail;
use App\Models\Communication\CommunicationNote;
use App\Models\Integration\ExternalReference;
use App\Models\Mail\EmailConnection;
use App\Models\Platform\User;
use App\Services\Communication\CommunicationNoteService;
use App\Services\Mail\{MailIntakeService, MailSignature};
use App\Support\CarbonFmt;
use CommonToolkit\Helper\Data\{EmailHelper, WebLinkHelper};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB, Mail};
use Illuminate\Support\Str;

/**
 * Antwort auf eine eingegangene E-Mail-Notiz: Versand mit Faden-Kopfzeilen,
 * ausgehende Notiz beim selben Träger, Versandverweis mit der eigenen
 * Message-ID (Plugin `email`, Typ `message`) und Audit an der Ursprungsnotiz.
 * Fachmodule reagieren über {@see CommunicationNoteReplied} (Rechnungsnachfrage
 * gilt als beantwortet).
 */
final class CommunicationNoteReplyService {
    public function __construct(
        private readonly CommunicationNoteService $notes,
        private readonly MailSignature $signatures,
    ) {}

    /**
     * Adresse des Eingangspostfachs als Reply-To, damit die nächste Antwort des
     * Empfängers wieder im Mail-Eingang landet; der Snapshot nennt das Postfach
     * als „Name / Ordner“.
     */
    public function replyToFor(CommunicationNote $note, MailOrigin $origin): ?string {
        $name = trim(Str::before((string) ($origin->mailbox ?? ''), ' / '));
        if ($name === '') {
            return null;
        }
        $address = EmailConnection::query()->withoutGlobalScopes()
            ->where('organization_id', $note->organization_id)
            ->where('name', $name)
            ->where('active', true)
            ->value('mailbox_address');

        return is_string($address) && EmailHelper::isEmail($address) ? $address : null;
    }

    /** Betreff der Antwort: vorhandenes AW:/Re:/WG: bleibt, sonst das Präfix der Sprache. */
    public function replySubject(?string $subject): string {
        $subject = trim((string) $subject);
        if (preg_match('/^(aw|re|wg|fwd?|tr|sv|ant|r)\s*:/i', $subject) === 1) {
            return $subject;
        }

        return (string) __('communication.reply.subject_prefix') . $subject;
    }

    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     */
    public function reply(CommunicationNote $note, User $actor, array $to, array $cc, string $subject, string $body): CommunicationNote {
        $origin = $note->mailOrigin();
        $notable = $note->notable;
        if ($origin === null || ! $origin->isInbound() || ! $notable instanceof Model) {
            throw new CommunicationNoteNotReplyableException($note);
        }

        $messageId = Str::uuid()->toString() . '@' . (WebLinkHelper::getHost((string) config('app.url')) ?? 'workdiary.local');
        $replyTo = $this->replyToFor($note, $origin);

        $pending = Mail::to($to);
        if ($cc !== []) {
            $pending->cc($cc);
        }
        $text = $body . "\n\n" . $this->signatures->blockFor($actor, $note->organization) . "\n\n" . $this->quote($note, $origin);
        $pending->send(new CommunicationNoteReplyMail($subject, $text, $messageId, $origin->messageId, $replyTo));

        $reply = DB::transaction(function () use ($note, $notable, $actor, $to, $cc, $subject, $body, $origin, $messageId, $replyTo): CommunicationNote {
            $reply = $this->notes->create($notable, $actor, [
                'type' => CommunicationNoteType::Email->value,
                'direction' => CommunicationDirection::Outbound->value,
                'subject' => $subject,
                'body' => $body,
                'occurred_at' => 'now',
            ]);

            ExternalReference::query()->create([
                'organization_id' => $note->organization_id,
                'plugin_id' => MailIntakeService::PLUGIN_ID,
                'external_type' => MailIntakeService::EXTERNAL_TYPE,
                'referenceable_type' => $reply->getMorphClass(),
                'referenceable_id' => $reply->getKey(),
                'external_id' => '<' . $messageId . '>',
                'payload' => [
                    'direction' => CommunicationDirection::Outbound->value,
                    'message_id' => '<' . $messageId . '>',
                    'in_reply_to' => $origin->messageId,
                    'to' => $to,
                    'cc' => $cc,
                    'subject' => $subject,
                    'sent_at' => now()->toIso8601String(),
                    'reply_to' => $replyTo,
                ],
                'synced_at' => now(),
            ]);

            $note->audit('communication.replied', [
                'reply_note_id' => $reply->getKey(),
                'to' => $to,
                'cc' => $cc,
                'message_id' => $messageId,
            ]);

            return $reply;
        });

        CommunicationNoteReplied::dispatch((int) $note->organization_id, (int) $note->getKey(), (int) $reply->getKey(), (int) $actor->getKey());

        return $reply;
    }

    /** Ursprungsnachricht als Zitat unter der Antwort, wie Mail-Clients es tun. */
    private function quote(CommunicationNote $note, MailOrigin $origin): string {
        $header = (string) __('communication.reply.quote_header', [
            'date' => $origin->at !== null ? CarbonFmt::fdatetime($origin->at) : '—',
            'sender' => $origin->senderLabel(),
        ]);
        $lines = array_map(static fn (string $line): string => '> ' . $line, explode("\n", trim((string) $note->body)));

        return $header . "\n" . implode("\n", $lines);
    }
}
