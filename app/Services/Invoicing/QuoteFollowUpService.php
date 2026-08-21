<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteFollowUpService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\{Quote, User};
use App\Services\Communication\CommunicationNoteService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Nachfassen eines Angebots (Feature 112, MVP-601).
 *
 * Das Ergebnis wird als {@see \App\Models\CommunicationNote} festgehalten —
 * bewusst kein eigenes Modell: Ein Nachfassen IST ein Kontakt, und in der
 * Kundenakte steht es dort, wo man es sucht, neben Anrufen und Mails.
 *
 * Die Notiz hängt am **Kunden**, nicht am Angebot: Nur die Kundenakte zeigt
 * Kommunikationsnotizen an (die Whitelist des Notiz-Controllers kennt Quote
 * nicht), und die Vertriebshistorie eines Kunden ist genau die Frage, die
 * beim nächsten Angebot zählt. Die Angebotsnummer steht im Betreff.
 */
class QuoteFollowUpService {
    public function __construct(private readonly CommunicationNoteService $notes) {}

    /**
     * Nachfassen protokollieren.
     *
     * @param string|null $nextAt Neuer Nachfasstermin (Y-m-d) oder null = abgeschlossen
     */
    public function record(Quote $quote, User $actor, string $result, ?string $nextAt = null): Quote {
        if (! in_array($quote->status, ['approved', 'sent'], true)) {
            throw new RuntimeException((string) __('quotes.follow_up.wrong_status'));
        }

        $customer = $quote->customer;
        if ($customer === null) {
            throw new RuntimeException((string) __('quotes.follow_up.no_customer'));
        }

        return DB::transaction(function () use ($quote, $actor, $result, $nextAt, $customer): Quote {
            $this->notes->create($customer, $actor, [
                'type' => 'call',
                'direction' => 'outbound',
                'occurred_at' => CarbonImmutable::now()->toDateTimeString(),
                'subject' => (string) __('quotes.follow_up.note_subject', ['number' => (string) $quote->number]),
                'body' => $result,
                // Folgetermin auch an der Notiz: Wer die Kundenakte liest,
                // sieht dort, dass noch etwas ansteht — ohne ins Angebot zu
                // wechseln.
                'next_action' => $nextAt === null ? null : (string) __('quotes.follow_up.next_action', ['number' => (string) $quote->number]),
                'next_action_due_at' => $nextAt,
                'next_action_user_id' => $nextAt === null ? null : ($quote->follow_up_user_id ?? $actor->id),
            ]);

            $quote->forceFill([
                'follow_up_at' => $nextAt,
                // Ohne Folgetermin gilt das Nachfassen als erledigt; mit
                // Folgetermin beginnt die Uhr neu, deshalb wieder offen.
                'followed_up_at' => $nextAt === null ? CarbonImmutable::now() : null,
                'follow_up_user_id' => $nextAt === null ? $quote->follow_up_user_id : ($quote->follow_up_user_id ?? $actor->id),
            ])->save();

            $quote->audit('quote.followed_up', [
                'by' => $actor->id,
                'next_follow_up_at' => $nextAt,
            ]);

            return $quote->refresh();
        });
    }
}
