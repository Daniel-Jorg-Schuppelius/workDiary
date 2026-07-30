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
 * (Feature 056, MVP-117) — Inbox-First, nie blind anlegen: Dublettenschutz über
 * die Message-ID (Unique-Index org+plugin+dedupe_key), Kunden-Zuordnung nur als
 * Vorschlag ({@see EntityMatcher}/{@see CustomerMatchProfile}; Treffer →
 * ambiguous, sonst unmatched), Herkunftsnachweis im `remote_snapshot` (DoD 056).
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
     * @return 'created'|'skipped'|'ticket_message'|'einvoice'|'b2b_order'
     */
    public function intake(Organization $organization, EmailConnection $connection, ParsedMessage $message): string {
        // openTRANS-Bestellungen (Feature 099, MVP-458): XML-Anhänge zuerst als
        // B2B-Bestellung versuchen — VOR der E-Rechnungs-Pipeline, sonst frisst
        // die den XML-Anhang. Nur bei aktivem Modul; kein openTRANS → normaler Weg.
        if ($message->attachments !== []
            && app(\App\Services\Licensing\ModuleStatusResolver::class)->isActiveFor($organization, 'module.b2b_katalog')) {
            $result = $this->intakeB2bOrders($organization, $message);
            if ($result !== null) {
                return $result;
            }
        }

        // E-Rechnungs-Postfach (Feature 066, MVP-165): Anhänge laufen durch dieselbe
        // Eingangsverarbeitung wie der Upload; nicht lesbare Nachrichten fallen in die normale Inbox durch.
        if ($connection->einvoice_intake && $message->attachments !== []) {
            $result = $this->intakeEInvoices($organization, $connection, $message);
            if ($result !== null) {
                return $result;
            }
        }

        // Ticket-Pipeline (Feature 065, P2): bei Queue-Postfach greift zuerst das
        // Mail-Threading (In-Reply-To/References oder Ticket-Nr. im Betreff → bestehendes Ticket).
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
                $conversation = app(\App\Services\ServiceTicket\TicketConversationService::class);
                $ticketMessage = $conversation->inbound(
                    $ticket,
                    $message->body,
                    'mail',
                    $message->messageId,
                    $message->inReplyTo,
                    $message->subject,
                );

                // Anhänge der Kundenmail (MVP-152): dieselbe Whitelist-/Größen-Policy wie beim Inbox-Intake, idempotent kopiert.
                if ($message->attachments !== []) {
                    $stored = $this->attachments->persistFromMessage($organization, $message);
                    $conversation->attachStoredMailAttachments($ticketMessage, $stored);
                }

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
     * openTRANS-Bestellungen übernehmen (Feature 099, MVP-458, Mail-Kanal):
     * jede XML-Anlage wird als openTRANS-2.1-ORDER versucht; kein Treffer →
     * `null` (andere Pipelines/normaler Inbox-Weg). Dubletten (ORDER-ID +
     * Käufer) erzeugen keinen zweiten Vorschlag.
     *
     * @return 'b2b_order'|'skipped'|null
     */
    private function intakeB2bOrders(Organization $organization, ParsedMessage $message): ?string {
        $service = app(\App\Services\B2bCatalog\B2bOrderIntakeService::class);
        $created = 0;
        $duplicates = 0;
        foreach ($message->attachments as $attachment) {
            $isXml = str_contains($attachment->mime, 'xml')
                || str_ends_with(strtolower($attachment->filename), '.xml');
            if (! $isXml) {
                continue;
            }
            try {
                $result = $service->intake($organization, $attachment->content, \App\Models\B2b\B2bOrder::SOURCE_MAIL);
            } catch (\RuntimeException) {
                continue; // kein openTRANS-ORDER → andere Pipelines versuchen
            }
            $result['status'] === 'created' ? $created++ : $duplicates++;
        }

        if ($created > 0) {
            return 'b2b_order';
        }
        if ($duplicates > 0) {
            return 'skipped'; // bereits erfasst — kein zweiter Vorschlag
        }

        return null;
    }

    /**
     * E-Rechnungs-Anhänge übernehmen (MVP-165, Mail-Kanal): jede XML-/PDF-
     * Anlage wird als E-Rechnung versucht; Dubletten (SHA-256) werden
     * übersprungen. `null` = kein verwertbarer Anhang → normaler Inbox-Weg.
     *
     * @return 'einvoice'|'skipped'|null
     */
    private function intakeEInvoices(Organization $organization, EmailConnection $connection, ParsedMessage $message): ?string {
        $actor = \App\Models\User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $connection->organization_id)
            ->where('id', (int) $connection->created_by)
            ->first();
        if ($actor === null) {
            return null; // ohne zuordenbaren Bearbeiter kein automatischer DMS-Eintrag
        }

        $service = app(\App\Services\Invoicing\EInvoice\IncomingEInvoiceService::class);
        $created = 0;
        $duplicates = 0;
        foreach ($message->attachments as $attachment) {
            $isCandidate = str_contains($attachment->mime, 'xml')
                || str_contains($attachment->mime, 'pdf')
                || str_ends_with(strtolower($attachment->filename), '.xml')
                || str_ends_with(strtolower($attachment->filename), '.pdf');
            if (! $isCandidate) {
                continue;
            }

            $result = $service->storeIncoming(
                $actor,
                $attachment->content,
                $attachment->mime,
                null,
                'mail',
                null,
                $attachment->filename,
            );
            if ($result['status'] === 'created') {
                $created++;
            } elseif ($result['status'] === 'duplicate') {
                $duplicates++;
            }
        }

        if ($created > 0) {
            return 'einvoice';
        }
        if ($duplicates > 0) {
            return 'skipped'; // bereits erfasst — kein zweites Document, kein Inbox-Item
        }

        return null;
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
