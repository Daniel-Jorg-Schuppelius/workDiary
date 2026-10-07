<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerIntakeQuoteService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Intake;

use App\Enums\Customer\IntakeStatus;
use App\Enums\Notification\NotificationEvent;
use App\Enums\Sales\QuoteStatus;
use App\Models\Customer\CustomerIntake;
use App\Models\Platform\User;
use App\Models\Sales\{Quote, QuoteItem};
use App\Services\Invoicing\QuoteService;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\{ErrorText, Sqid};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Angebot am Kundeneingang (MVP-1075): genau ein lokales Angebot je Eingang.
 * Der Kunde entscheidet im Portal über denselben Annahmeweg wie beim
 * Token-Link ({@see QuoteService::accept()} mit eingefrorenem Stand). Eine
 * überarbeitete Fassung wird neu verknüpft — eine Zustimmung wandert nie
 * auf eine andere Fassung.
 */
class CustomerIntakeQuoteService {
    /** Lizenz der Angebote (Vertriebsmodul). */
    public const MODULE = 'module.vertrieb';

    public function __construct(
        private readonly QuoteService $quotes,
        private readonly FeatureFlagResolver $features,
        private readonly CustomerIntakeService $intakes,
        private readonly CustomerIntakeNotifier $notifier,
    ) {}

    public function available(): bool {
        return $this->features->isEnabled(self::MODULE);
    }

    /** Kann der Kunde das Angebot sehen? Entwürfe und interne Freigaben nicht. */
    public function visibleToCustomer(Quote $quote): bool {
        return ! in_array($quote->status, [QuoteStatus::Draft, QuoteStatus::Approved], true);
    }

    /** Gibt es eine neuere Fassung dieses Angebots? */
    public function isSuperseded(Quote $quote): bool {
        return Quote::query()->withoutGlobalScopes()->where('previous_version_id', $quote->id)->exists();
    }

    /** Darf der Kunde jetzt entscheiden? Versandt, nicht abgelaufen, nicht überholt. */
    public function decidable(Quote $quote): bool {
        return $quote->status === QuoteStatus::Sent && ! $quote->isExpired() && ! $this->isSuperseded($quote);
    }

    public function link(CustomerIntake $intake, Quote $quote, User $actor): CustomerIntake {
        $this->assertAvailable();
        $this->assertChangeable($intake);
        if ((int) $quote->organization_id !== (int) $intake->organization_id || (int) $quote->customer_id !== (int) $intake->customer_id) {
            throw ValidationException::withMessages(['quote_id' => (string) __('customer_intake.error.quote_foreign')]);
        }
        $taken = CustomerIntake::query()->withoutGlobalScopes()
            ->where('quote_id', $quote->id)->whereKeyNot($intake->id)->exists();
        if ($taken) {
            throw ValidationException::withMessages(['quote_id' => (string) __('customer_intake.error.quote_taken')]);
        }

        return DB::transaction(function () use ($intake, $quote, $actor): CustomerIntake {
            $intake->forceFill(['quote_id' => $quote->id]);
            if ($intake->status === IntakeStatus::Submitted) {
                $intake->status = IntakeStatus::InProgress;
            }
            $intake->save();
            $intake->record('quote_linked', ['quote_id' => $quote->id, 'number' => $quote->number, 'version' => $quote->version], $actor);
            $quote->audit('quote.intake_linked', ['customer_intake_id' => $intake->id, 'number' => $intake->number]);

            return $intake->setRelation('quote', $quote);
        });
    }

    /** Neues Angebot für den Kunden des Eingangs anlegen und verknüpfen; Positionen pflegt der Betrieb am Angebot. */
    public function create(CustomerIntake $intake, User $actor): Quote {
        $this->assertAvailable();
        $this->assertChangeable($intake);

        return DB::transaction(function () use ($intake, $actor): Quote {
            $quote = $this->quotes->create(['customer_id' => $intake->customer_id], [], $actor);
            $this->link($intake, $quote, $actor);

            return $quote;
        });
    }

    public function unlink(CustomerIntake $intake, User $actor): CustomerIntake {
        $this->assertChangeable($intake);
        $quote = $intake->quote;
        if ($quote === null) {
            return $intake;
        }

        return DB::transaction(function () use ($intake, $quote, $actor): CustomerIntake {
            $intake->forceFill(['quote_id' => null])->save();
            $intake->record('quote_unlinked', ['quote_id' => $quote->id, 'number' => $quote->number, 'version' => $quote->version], $actor);

            return $intake->setRelation('quote', null);
        });
    }

    /** Kunde auf das versandte Angebot hinweisen (Portal und Mail). */
    public function announce(CustomerIntake $intake, User $actor): void {
        $quote = $intake->quote;
        if ($quote === null || ! $this->decidable($quote)) {
            throw ValidationException::withMessages(['quote_id' => (string) __('customer_intake.error.quote_not_sent')]);
        }
        $intake->record('quote_announced', ['quote_id' => $quote->id, 'number' => $quote->number], $actor);
        $this->notifier->mailCustomer($intake, CustomerIntakeNotifier::QUOTE);
    }

    /**
     * Entscheidung des Kunden im Portal: Annahme (optional Wahl der Optionen
     * bzw. Alternativen) oder Ablehnung.
     *
     * @param  list<string>  $itemSqids  gewählte Positionen; leer = Vollannahme der Pflichtpositionen
     */
    public function decide(CustomerIntake $intake, User $portalUser, bool $accept, array $itemSqids = [], ?string $reason = null): Quote {
        $quote = $intake->quote;
        if (! $intake->status->isOpen() || $quote === null || ! $this->decidable($quote)) {
            throw ValidationException::withMessages(['decision' => (string) __('customer_intake.error.quote_not_decidable')]);
        }

        try {
            $decided = DB::transaction(function () use ($intake, $quote, $portalUser, $accept, $itemSqids, $reason): Quote {
                if ($accept) {
                    $itemIds = null;
                    if ($itemSqids !== []) {
                        // Nur Sqids, kein numerischer Fallback; nur Positionen dieses Angebots.
                        $requested = array_filter(array_map(static fn (string $sqid): ?int => Sqid::decode(QuoteItem::class, $sqid), $itemSqids));
                        $itemIds = $quote->items()->whereIn('id', $requested)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
                    }
                    $decided = $this->quotes->accept($quote, $itemIds);
                } else {
                    $decided = $this->quotes->reject($quote, $reason);
                }
                $decided->audit('quote.portal_decision', ['portal_user_id' => $portalUser->id, 'customer_intake_id' => $intake->id]);
                $intake->record($accept ? 'quote_accepted' : 'quote_rejected', [
                    'quote_id' => $decided->id,
                    'number' => $decided->number,
                    'version' => $decided->version,
                    'partial' => $decided->status === QuoteStatus::PartiallyAccepted,
                    'reason' => $accept ? null : (trim((string) $reason) ?: null),
                ], $portalUser);
                $this->intakes->resumeAfterCustomer($intake);

                return $decided;
            });
        } catch (\RuntimeException $e) {
            // Fachliche Ablehnung des Angebotsdiensts (Bindefrist, Status) — Datenbankfehler bleiben Fehler.
            if ($e instanceof \Illuminate\Database\QueryException) {
                throw $e;
            }
            throw ValidationException::withMessages(['decision' => ErrorText::for($e)]);
        }
        $this->notifier->notifyStaff($intake, NotificationEvent::CustomerIntakeActivity, 'quote_decided_message');

        return $decided;
    }

    private function assertAvailable(): void {
        if (! $this->available()) {
            throw ValidationException::withMessages(['quote_id' => (string) __('customer_intake.error.quotes_unavailable')]);
        }
    }

    /** Angebot nur vor der Übernahme und nie nach einer Annahme austauschen. */
    private function assertChangeable(CustomerIntake $intake): void {
        if (! $intake->status->isOpen()) {
            throw ValidationException::withMessages(['quote_id' => (string) __('customer_intake.error.closed')]);
        }
        if ($intake->quote?->status->isWon() ?? false) {
            throw ValidationException::withMessages(['quote_id' => (string) __('customer_intake.error.quote_already_accepted')]);
        }
    }
}
