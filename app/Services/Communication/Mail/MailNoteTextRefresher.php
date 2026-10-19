<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailNoteTextRefresher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Communication\Mail;

use App\Enums\Communication\MailTextRefreshOutcome;
use App\Models\Communication\CommunicationNote;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Mail\EmailConnection;
use App\Services\Mail\{MailIntakeService, MailboxGateway};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Text einer eingegangenen E-Mail-Notiz aus dem Postfach erneuern: Notizen vor
 * dem 2026-10-19 tragen den Text ohne Zeilenstruktur (die Gateways hatten HTML
 * nur von Tags befreit). Die Mail wird über ihre Message-ID erneut gelesen —
 * zuerst im Postfach des Snapshots, sonst in allen aktiven Postfächern der
 * Organisation — und der Text samt Snapshot und Herkunftsverweis neu gesetzt.
 */
final class MailNoteTextRefresher {
    public function __construct(private readonly MailboxGateway $gateway) {}

    public function refresh(CommunicationNote $note, bool $dryRun = false): MailTextRefreshOutcome {
        $origin = $note->mailOrigin();
        if ($origin === null || ! $origin->isInbound() || $origin->messageId === null) {
            return MailTextRefreshOutcome::NotApplicable;
        }
        $connections = $this->connectionsFor($note, $origin);
        if ($connections === []) {
            return MailTextRefreshOutcome::NotApplicable;
        }

        $message = null;
        foreach ($connections as $connection) {
            $message = $this->gateway->find($connection, $origin->messageId);
            if ($message !== null) {
                break;
            }
        }
        if ($message === null) {
            return MailTextRefreshOutcome::NotFound;
        }

        $body = trim($message->body);
        if ($body === '' || $body === trim((string) $note->body)) {
            return MailTextRefreshOutcome::Unchanged;
        }
        if ($dryRun) {
            return MailTextRefreshOutcome::Refreshed;
        }

        DB::transaction(function () use ($note, $origin, $body): void {
            $note->forceFill(['body' => $body])->save();

            ExternalReference::query()->withoutGlobalScopes()
                ->where('organization_id', $note->organization_id)
                ->where('plugin_id', MailIntakeService::PLUGIN_ID)
                ->where('external_type', MailIntakeService::EXTERNAL_TYPE)
                ->forReferenceable($note)
                ->get()
                ->each(function (ExternalReference $reference) use ($body): void {
                    $payload = (array) $reference->payload;
                    if (array_key_exists('body', $payload)) {
                        $payload['body'] = Str::limit($body, 20000);
                        $reference->forceFill(['payload' => $payload])->save();
                    }
                });

            IntegrationInboxItem::query()->withoutGlobalScopes()
                ->where('organization_id', $note->organization_id)
                ->where('plugin_id', MailIntakeService::PLUGIN_ID)
                ->whereIn('dedupe_key', ['message:' . $origin->messageId, 'message:<' . $origin->messageId . '>'])
                ->get()
                ->each(function (IntegrationInboxItem $item) use ($body): void {
                    $snapshot = (array) $item->remote_snapshot;
                    if (array_key_exists('body', $snapshot)) {
                        $snapshot['body'] = Str::limit($body, 20000);
                        $item->forceFill(['remote_snapshot' => $snapshot])->save();
                    }
                });

            $note->audit('communication.mail_text_refreshed', ['message_id' => $origin->messageId, 'length' => mb_strlen($body)]);
        });

        return MailTextRefreshOutcome::Refreshed;
    }

    /**
     * Das Postfach des Snapshots zuerst („Name / Ordner“), sonst alle aktiven
     * Postfächer der Organisation.
     *
     * @return list<EmailConnection>
     */
    private function connectionsFor(CommunicationNote $note, MailOrigin $origin): array {
        $query = EmailConnection::query()->withoutGlobalScopes()
            ->where('organization_id', $note->organization_id)
            ->where('active', true)
            ->orderBy('id');
        $name = trim(Str::before((string) ($origin->mailbox ?? ''), ' / '));
        $all = array_values($query->get()->all());
        if ($name === '') {
            return $all;
        }
        $named = array_values(array_filter($all, static fn (EmailConnection $c): bool => trim((string) $c->name) === $name));
        $rest = array_values(array_filter($all, static fn (EmailConnection $c): bool => trim((string) $c->name) !== $name));

        return [...$named, ...$rest];
    }
}
