<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailIntakeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\{Customer, EmailConnection, IntegrationInboxItem, Organization};
use App\Services\Integration\Match\{EntityMatcher, MatchStrategy};
use App\Services\Integration\Profiles\CustomerMatchProfile;
use Illuminate\Database\Eloquent\Model;

/**
 * Nimmt eine geparste Eingangsnachricht in die Integrations-Inbox auf
 * (Feature 056, MVP-117) — **Inbox-First, nie blind anlegen**:
 *
 * - **Dublettenschutz** über die Message-ID (`dedupe_key = 'message:…'`,
 *   abgesichert durch den Unique-Index org+plugin+dedupe_key).
 * - **Zuordnung** der Absenderadresse zu einem Kunden über den
 *   {@see EntityMatcher} ({@see CustomerMatchProfile}); E-Mail matcht als
 *   `likely` → landet als **ambiguous** (Mensch entscheidet), ohne Treffer als
 *   **unmatched**. Es entsteht nie automatisch ein Datensatz.
 * - **Herkunftsnachweis** (Postfach, Message-ID) im `remote_snapshot` (DoD 056).
 */
class MailIntakeService {
    public const PLUGIN_ID = 'email';

    public const EXTERNAL_TYPE = 'message';

    public function __construct(
        private readonly EntityMatcher $matcher,
        private readonly CustomerMatchProfile $profile,
        private readonly MailReferenceMatcher $references,
        private readonly MailAttachmentStore $attachments,
    ) {}

    /**
     * @return 'created'|'skipped'|'ticket_message'
     */
    public function intake(Organization $organization, EmailConnection $connection, ParsedMessage $message): string {
        // Ticket-Pipeline (Feature 065, P2): Ist das Postfach einer Queue
        // zugeordnet, greift zuerst das Mail-Threading — eine Antwort auf
        // eine bekannte Ticket-Nachricht (In-Reply-To/References) oder eine
        // Ticket-Nummer im Betreff hängt die Mail ans bestehende Ticket.
        $queue = \App\Models\ServiceQueue::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $connection->organization_id)
            ->where('email_connection_id', $connection->id)
            ->first();
        if ($queue !== null) {
            $ticket = $this->matchThreadedTicket($organization, $message);
            if ($ticket !== null) {
                // Dedupe auch hier über die Message-ID.
                $seen = \App\Models\ServiceTicketMessage::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('message_id', $message->messageId)
                    ->exists();
                if ($seen) {
                    return 'skipped';
                }
                app(\App\Services\ServiceTicket\TicketConversationService::class)->inbound(
                    $ticket,
                    $message->body,
                    'mail',
                    $message->messageId,
                    $message->inReplyTo,
                    $message->subject,
                );

                return 'ticket_message';
            }
        }

        $item = IntegrationInboxItem::query()->withoutGlobalScopes()->firstOrNew([
            'organization_id' => $connection->organization_id,
            'plugin_id' => self::PLUGIN_ID,
            'dedupe_key' => 'message:' . $message->messageId,
        ]);
        if ($item->exists) {
            return 'skipped'; // bereits gesehen (Message-ID)
        }

        $candidates = $this->matcher->match($organization, $this->profile, [
            'email' => $message->fromEmail,
            'name' => $message->fromName,
        ])->candidates();

        // Zusätzliche Kandidaten aus Referenznummern in Betreff + Text
        // (erhöht nur die Konfidenz, nie Auto-Zuordnung).
        $candidates = $this->mergeCandidates(
            $candidates,
            $this->references->customerCandidates($organization, $message->subject . "\n" . $message->body),
        );

        $best = $candidates[0]['model'] ?? null;

        // Herkunftsnachweis + beim Intake persistierte Anhänge (Whitelist/Größe;
        // abgelehnte mit Grund). Nie Auto-Übernahme — erst bei der Auflösung.
        $snapshot = $message->snapshot($connection);
        if ($queue !== null) {
            $snapshot['service_queue_id'] = $queue->id;
            $snapshot['auto_submitted'] = $message->isAutoSubmitted; // Loop-Schutz: nie Auto-Antwort
            $snapshot['mail_message_id'] = $message->messageId;
        }
        $attachments = $this->attachments->persistFromMessage($organization, $message);
        if ($attachments !== []) {
            $snapshot['attachments'] = $attachments;
        }

        $item->fill([
            'source' => 'imap',
            'target_type' => (new Customer())->getMorphClass(),
            'external_type' => self::EXTERNAL_TYPE,
            'external_id' => $message->messageId,
            'case_type' => $candidates !== [] ? IntegrationInboxItem::CASE_AMBIGUOUS : IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'referenceable_type' => $best instanceof Model ? $best->getMorphClass() : null,
            'referenceable_id' => $best instanceof Model ? $best->getKey() : null,
            'candidate_ids' => $this->candidatePayload($candidates),
            'remote_snapshot' => $snapshot,
            'display_title' => $message->subject !== '' ? $message->subject : __('mail.inbox.no_subject'),
            'display_subtitle' => $message->fromEmail,
            'occurred_at' => $message->receivedAt,
        ])->save();

        return 'created';
    }

    /**
     * Threading (Feature 065, P2): In-Reply-To/References gegen bekannte
     * Ticket-Nachrichten, dann Ticket-Nummer im Betreff ([TICKET-NO]).
     * Nur org-eigene Treffer — fremde Message-IDs (Spoofing) laufen ins Leere.
     */
    private function matchThreadedTicket(Organization $organization, ParsedMessage $message): ?\App\Models\ServiceTicket {
        $referencedIds = array_values(array_filter([$message->inReplyTo, ...$message->references]));
        if ($referencedIds !== []) {
            $known = \App\Models\ServiceTicketMessage::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereIn('message_id', $referencedIds)
                ->orderByDesc('id')
                ->first();
            if ($known !== null) {
                return $known->ticket()->withoutGlobalScopes()->first();
            }
        }

        if (preg_match('/\[([A-Z0-9\-\/]{4,30})\]/i', $message->subject, $matches) === 1) {
            return \App\Models\ServiceTicket::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('ticket_no', $matches[1])
                ->first();
        }

        return null;
    }

    /**
     * @param  list<array{model: Model, confidence: string, reasons: list<string>}>  $candidates
     * @return list<array<string, mixed>>
     */
    private function candidatePayload(array $candidates): array {
        $out = [];
        foreach ($candidates as $candidate) {
            $model = $candidate['model'];
            $out[] = [
                'id' => $model->getKey(),
                'sqid' => $model->getRouteKey(),
                'label' => (string) ($model->getAttribute('name') ?? $model->getAttribute('company') ?? ''),
                'confidence' => $candidate['confidence'],
                'reasons' => $candidate['reasons'],
            ];
        }

        return $out;
    }

    /**
     * Führt Sender- und Referenz-Kandidaten zusammen: je Modell einmal, Gründe
     * vereint, höchste Konfidenz gewinnt; anschließend nach Konfidenz absteigend
     * (der stärkste Treffer ist der Default-Vorschlag, bleibt aber ein Vorschlag).
     *
     * @param  list<array{model: Model, confidence: string, reasons: list<string>}>  $base
     * @param  list<array{model: Model, confidence: string, reasons: list<string>}>  $extra
     * @return list<array{model: Model, confidence: string, reasons: list<string>}>
     */
    private function mergeCandidates(array $base, array $extra): array {
        $rank = [MatchStrategy::FUZZY => 1, MatchStrategy::LIKELY => 2, MatchStrategy::EXACT => 3];

        /** @var array<int|string, array{model: Model, confidence: string, reasons: list<string>}> $byId */
        $byId = [];
        foreach ([...$base, ...$extra] as $candidate) {
            $id = $candidate['model']->getKey();
            if (! isset($byId[$id])) {
                $byId[$id] = $candidate;

                continue;
            }
            $existing = $byId[$id];
            $existing['reasons'] = array_values(array_unique([...$existing['reasons'], ...$candidate['reasons']]));
            if (($rank[$candidate['confidence']] ?? 0) > ($rank[$existing['confidence']] ?? 0)) {
                $existing['confidence'] = $candidate['confidence'];
            }
            $byId[$id] = $existing;
        }

        $merged = array_values($byId);
        usort($merged, static fn (array $a, array $b): int => ($rank[$b['confidence']] ?? 0) <=> ($rank[$a['confidence']] ?? 0));

        return $merged;
    }
}
