<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalRequestService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Rental;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Rental\{RentalRequestStatus, RentalReservationKind};
use App\Exceptions\RentalConflictException;
use App\Mail\RentalRequestDecisionMail;
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Invoicing\OnlinePayment;
use App\Models\Platform\{Organization, User};
use App\Models\Rental\{RentalCase, RentalProfile, RentalRequest, RentalReservation};
use App\Services\Concerns\AssertsStatusTransition;
use App\Services\Invoicing\OnlinePayment\OnlinePaymentService;
use App\Services\Notification\NotificationDispatcher;
use App\Support\{CanonicalUrl, CarbonFmt, DocumentLocale};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Log, Mail};
use RuntimeException;

/**
 * Zweiphasige Verleih-Anfrage aus dem Kundenportal (Feature 073, MVP-714,
 * Muster {@see \App\Services\Appointments\AppointmentRequestService}):
 * Der Kunde FRAGT Gerät oder Gerätegruppe für einen Zeitraum an, die
 * Leitung ENTSCHEIDET — erst die Annahme erzeugt Verleihakte (Entwurf) und
 * Vormerkung, beides über die bestehenden Schreibstellen
 * ({@see RentalCaseService::open()}, {@see RentalAvailabilityService::createWindow()}).
 * Es gibt keinen Weg von `requested` in den Kalender ohne `decided_by`.
 */
class RentalRequestService {
    use AssertsStatusTransition;

    /** Haltezeit einer unbezahlten Direktbuchung, wenn die Organisation keine nennt (Minuten). */
    public const int DEFAULT_HOLD_MINUTES = 30;

    public function __construct(
        private readonly RentalAvailabilityService $availability,
        private readonly RentalCaseService $cases,
        private readonly NotificationDispatcher $notifier,
        private readonly RentalBillingService $billing,
        private readonly OnlinePaymentService $payments,
    ) {}

    /**
     * Fürs Portal freigegebenes Sortiment (Default-Deny: nur Profile mit
     * `portal_bookable`, zusätzlich leihfähig) — org-gescopt über den Kunden.
     *
     * @return Collection<int, RentalProfile>
     */
    public function bookableProfiles(Customer $customer): Collection {
        return RentalProfile::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $customer->organization_id)
            ->portalBookable()
            ->with('asset:id,name,organization_id')
            ->get()
            ->filter(fn (RentalProfile $p): bool => $p->asset !== null)
            ->sortBy(fn (RentalProfile $p): string => (string) $p->asset?->name)
            ->values();
    }

    /**
     * Grobe Verfügbarkeit fürs Portal: nur frei/belegt — Konflikt-Details
     * (Fremdkunde, Akte, Art der Belegung) verlassen den Service nie.
     */
    public function isRoughlyAvailable(Asset $asset, Carbon $from, Carbon $to): bool {
        return $this->availability->isAvailable($asset, $from, $to);
    }

    /** Portal-Anfrage anlegen (Status requested — nie mehr). */
    public function requestFromPortal(Customer $customer, User $portalUser, ?Asset $asset, ?string $groupCode, Carbon $from, Carbon $to, ?string $note = null, ?int $guests = null): RentalRequest {
        $this->assertPortalRequest($customer, $asset, $groupCode, $from, $to, $guests);

        $request = RentalRequest::query()->create([
            'organization_id' => $customer->organization_id,
            'customer_id' => $customer->id,
            'portal_user_id' => $portalUser->id,
            'asset_id' => $asset?->id,
            'group_code' => $asset === null ? $groupCode : null,
            'starts_at' => $from,
            'ends_at' => $to,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'status' => RentalRequestStatus::Requested->value,
            'guests' => $guests,
        ]);
        $request->audit('rental.requested', ['asset_id' => $asset?->id, 'group_code' => $request->group_code, 'by_portal_user_id' => (int) $portalUser->id]);

        $this->notifyRequested($request, $customer);

        return $request;
    }

    /** Direktbuchung im Portal freigeschaltet (MVP-916, Einstellung der Organisation). */
    public function directBookingEnabled(Organization $organization): bool {
        return (bool) data_get($organization->settings, 'rental.portal_direct_booking', false);
    }

    /**
     * Direktbuchung aus dem Portal (MVP-916): nur ein bestimmtes, freigegebenes
     * Gerät. Verleihakte und harte Reservierung entstehen sofort über die
     * bestehenden Schreibstellen; {@see RentalCaseService::reserve()} prüft
     * Sperren und Doppelbuchung unter Sperre. Die Leitung wird benachrichtigt,
     * bei Online-Vorauszahlung (MVP-1515) erst mit dem Zahlungseingang.
     *
     * @throws RuntimeException|RentalConflictException
     */
    public function bookFromPortal(Customer $customer, User $portalUser, Asset $asset, Carbon $from, Carbon $to, ?string $note = null, ?int $guests = null): RentalRequest {
        $organization = Organization::query()->withoutGlobalScopes()->findOrFail($customer->organization_id);
        if (! $this->directBookingEnabled($organization)) {
            throw new RuntimeException((string) __('rental.portal.direct_disabled'));
        }
        $this->assertPortalRequest($customer, $asset, null, $from, $to, $guests);
        $note = $note !== null && trim($note) !== '' ? trim($note) : null;

        // Vorauszahlung (MVP-1515): ohne Zahlungsanbieter keine verbindliche Direktbuchung.
        $profile = RentalProfile::query()->withoutGlobalScopes()->where('asset_id', $asset->id)->first();
        $prepayment = $profile instanceof RentalProfile ? $this->billing->prepaymentAmount($profile, $customer, $from, $to) : null;
        if ($prepayment !== null && ! $this->payments->available($organization)) {
            throw new RuntimeException((string) __('rental.prepayment.unavailable'));
        }

        $request = DB::transaction(function () use ($organization, $customer, $portalUser, $asset, $from, $to, $note, $guests, $profile, $prepayment): RentalRequest {
            $case = $this->cases->open($organization, $portalUser, [
                'customer_id' => $customer->id,
                'starts_at' => $from,
                'ends_at' => $to,
                'rental_rate_card_id' => $profile?->default_rate_card_id,
                'notes' => $note !== null
                    ? (string) __('Portal-Anfrage: :note', ['note' => $note])
                    : (string) __('rental.portal.direct_case_note'),
            ], [$asset->id]);
            $this->cases->reserve($case, $portalUser);

            $request = RentalRequest::query()->create([
                'organization_id' => $customer->organization_id,
                'customer_id' => $customer->id,
                'portal_user_id' => $portalUser->id,
                'asset_id' => $asset->id,
                'starts_at' => $from,
                'ends_at' => $to,
                'note' => $note,
                // Mit Vorauszahlung hält die Reservierung nur bis zur Zahlungsfrist.
                'status' => $prepayment !== null ? RentalRequestStatus::AwaitingPayment->value : RentalRequestStatus::Accepted->value,
                'is_direct' => true,
                'guests' => $guests,
                'decided_at' => $prepayment !== null ? null : Carbon::now(),
                'prepayment_amount' => $prepayment,
                'currency' => $prepayment?->getCurrency()->value,
                'payment_due_at' => $prepayment !== null ? Carbon::now()->addMinutes($this->holdMinutes($organization)) : null,
                'rental_reservation_id' => RentalReservation::query()->withoutGlobalScopes()->where('rental_case_id', $case->id)->value('id'),
                'rental_case_id' => $case->id,
            ]);
            $request->audit('rental.directBooked', ['rental_case_id' => $case->id, 'asset_id' => $asset->id, 'by_portal_user_id' => (int) $portalUser->id, 'prepayment' => $prepayment?->getAmount()]);

            return $request;
        });
        if ($prepayment === null) {
            $this->notifyRequested($request, $customer);
        }

        return $request;
    }

    /** Haltezeit unbezahlter Direktbuchungen der Organisation in Minuten. */
    public function holdMinutes(Organization $organization): int {
        $minutes = (int) data_get($organization->settings, 'rental.prepayment_hold_minutes', self::DEFAULT_HOLD_MINUTES);

        return max(10, min(1440, $minutes > 0 ? $minutes : self::DEFAULT_HOLD_MINUTES));
    }

    /**
     * Bezahlseite der Vorauszahlung (MVP-1515) — gleich nach der Buchung und
     * erneut über „Jetzt bezahlen“, solange die Frist läuft.
     *
     * @throws RuntimeException|\App\Services\Invoicing\OnlinePayment\Exceptions\OnlinePaymentException
     */
    public function prepaymentCheckoutUrl(RentalRequest $request): string {
        if (! $request->isAwaitingPayment() || $request->prepayment_amount === null) {
            throw new RuntimeException((string) __('rental.prepayment.not_due'));
        }
        if ($request->payment_due_at !== null && $request->payment_due_at->isPast()) {
            throw new RuntimeException((string) __('rental.prepayment.expired_error'));
        }

        $organization = Organization::query()->withoutGlobalScopes()->findOrFail($request->organization_id);
        $customer = Customer::query()->withoutGlobalScopes()->findOrFail($request->customer_id);
        $housing = $request->asset?->rentalProfile?->isHousing() === true;

        return $this->payments->prepaymentCheckoutUrl(
            $request,
            $organization,
            $request->prepayment_amount,
            (string) __('rental.prepayment.description', [
                'subject' => $request->subjectLabel(),
                'from' => CarbonFmt::fdatetime($request->starts_at, \App\Support\Tz::ofOrganization($organization)),
            ]),
            CanonicalUrl::route($housing ? 'customer.housing.index' : 'customer.rentals.requests.index', ['zahlung' => $request->sqid]),
            DocumentLocale::for($customer, $organization),
            $request->portalUser?->email,
        );
    }

    /**
     * Zahlungseingang (MVP-1515): erst jetzt ist die Direktbuchung bestätigt.
     * Eine Zahlung nach Fristablauf bestätigt nichts — die Leitung entscheidet.
     */
    public function confirmPrepaid(RentalRequest $request, OnlinePayment $payment): void {
        $customer = Customer::query()->withoutGlobalScopes()->find($request->customer_id);

        $outcome = DB::transaction(function () use ($request, $payment): string {
            RentalRequest::query()->withoutGlobalScopes()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $request->refresh();

            if ($request->isAwaitingPayment()) {
                $this->assertStatusTransition($request->status, RentalRequestStatus::Accepted);
                $request->forceFill(['status' => RentalRequestStatus::Accepted->value, 'decided_at' => Carbon::now()])->save();
                $request->audit('rental.prepaymentReceived', ['online_payment_id' => $payment->id, 'amount' => $payment->gross_amount->getAmount()]);

                return 'confirmed';
            }
            if ($request->status === RentalRequestStatus::Accepted) {
                return 'known'; // zweiter Webhook derselben Zahlung
            }

            $request->audit('rental.prepaymentLate', ['online_payment_id' => $payment->id, 'status' => $request->status->value]);

            return 'late';
        });

        if (! $customer instanceof Customer || $outcome === 'known') {
            return;
        }
        if ($outcome === 'confirmed') {
            $this->notifyRequested($request, $customer);
            DB::afterCommit(fn () => $this->notifyDecision($request));

            return;
        }

        DB::afterCommit(fn () => $this->notifier->notify(NotificationEvent::RentalRequested, $request, null, [
            'title' => (string) __('rental.prepayment.late_notification', ['customer' => $customer->name]),
            'title_key' => 'rental.prepayment.late_notification',
            'title_params' => ['customer' => $customer->name],
            'message' => (string) __('rental.prepayment.late_message', ['subject' => $request->subjectLabel()]),
            'message_key' => 'rental.prepayment.late_message',
            'message_params' => ['subject' => $request->subjectLabel()],
            'url' => route('rental.requests.index'),
        ]));
    }

    /**
     * Unbezahlte Direktbuchungen nach Fristablauf freigeben (MVP-1515). Vorher
     * wird beim Anbieter nachgefragt — eine Zahlung ohne Webhook zählt noch.
     */
    public function expireUnpaid(): int {
        $expired = 0;
        RentalRequest::query()->withoutGlobalScopes()
            ->where('status', RentalRequestStatus::AwaitingPayment->value)
            ->where('payment_due_at', '<', Carbon::now())
            ->orderBy('id')
            ->each(function (RentalRequest $request) use (&$expired): void {
                $this->payments->refreshOpenFor($request);
                if ($this->expire($request->refresh())) {
                    $expired++;
                }
            });

        return $expired;
    }

    private function expire(RentalRequest $request): bool {
        return DB::transaction(function () use ($request): bool {
            RentalRequest::query()->withoutGlobalScopes()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $request->refresh();
            if (! $request->isAwaitingPayment()) {
                return false; // inzwischen bezahlt oder zurückgenommen
            }

            $this->assertStatusTransition($request->status, RentalRequestStatus::Expired);
            $request->forceFill(['status' => RentalRequestStatus::Expired->value])->save();
            $this->releaseCase($request, 'rental.prepayment.expired_reason');
            $request->audit('rental.prepaymentExpired', ['payment_due_at' => $request->payment_due_at?->toIso8601String()]);

            return true;
        });
    }

    /** Verleihakte und Reservierung einer nicht bestätigten Direktbuchung freigeben. */
    private function releaseCase(RentalRequest $request, string $reasonKey): void {
        $case = $request->rental_case_id !== null ? RentalCase::query()->withoutGlobalScopes()->find($request->rental_case_id) : null;
        if (! $case instanceof RentalCase || ! $case->status->canTransitionTo(\App\Enums\Rental\RentalCaseStatus::Cancelled)) {
            return;
        }
        $actor = $request->portalUser ?? User::query()->withoutGlobalScopes()->find($case->getAttribute('created_by'));
        if ($actor instanceof User) {
            $this->cases->cancel($case, $actor, (string) __($reasonKey));
        }
    }

    /** Gemeinsame Prüfung für Anfrage und Direktbuchung (Default-Deny über das freigegebene Sortiment). */
    private function assertPortalRequest(Customer $customer, ?Asset $asset, ?string $groupCode, Carbon $from, Carbon $to, ?int $guests = null): void {
        if ($asset === null && ($groupCode === null || $groupCode === '')) {
            throw new RuntimeException((string) __('Bitte ein Gerät oder eine Gerätegruppe wählen.'));
        }
        if ($to->lessThanOrEqualTo($from)) {
            throw new RuntimeException((string) __('Das Ende muss nach dem Beginn liegen.'));
        }
        if ($from->isPast()) {
            throw new RuntimeException((string) __('Der Zeitraum darf nicht in der Vergangenheit beginnen.'));
        }

        $bookable = $this->bookableProfiles($customer);
        if ($asset !== null && ! $bookable->contains(fn (RentalProfile $p): bool => (int) $p->asset_id === (int) $asset->id)) {
            throw new RuntimeException((string) __('Dieses Gerät ist im Portal nicht anfragbar.'));
        }
        $profile = $asset !== null ? $bookable->first(fn (RentalProfile $p): bool => (int) $p->asset_id === (int) $asset->id) : null;
        if ($profile?->isHousing() === true) {
            $zone = \App\Support\Tz::ofOrganization($customer->organization ?? abort(404));
            $localFrom = $from->copy()->setTimezone($zone);
            $localTo = $to->copy()->setTimezone($zone);
            $nights = (int) $localFrom->copy()->startOfDay()->diffInDays($localTo->copy()->startOfDay());
            if ($profile->rental_mode?->allowsHoliday() !== true || $nights < $profile->minimum_nights || $guests === null || $guests < 1 || $guests > $profile->max_guests
                || $localFrom->format('H:i') !== $profile->check_in_time || $localTo->format('H:i') !== $profile->check_out_time) {
                throw new RuntimeException((string) __('housing.capacity_error'));
            }
        }
        if ($asset === null && ! $bookable->contains(fn (RentalProfile $p): bool => $p->group_code === $groupCode)) {
            throw new RuntimeException((string) __('Diese Gerätegruppe ist im Portal nicht anfragbar.'));
        }
    }

    /**
     * Annahme durch die Leitung — erst hier entstehen Akte (Entwurf) und
     * Vormerkung. Bei Gruppenanfragen wählt die Leitung das Gerät.
     *
     * @throws RuntimeException|RentalConflictException
     */
    public function accept(RentalRequest $request, User $decider, ?Asset $asset = null): RentalRequest {
        if (! $request->isOpen()) {
            throw new RuntimeException((string) __('Diese Anfrage ist bereits entschieden.'));
        }

        $asset ??= $request->asset;
        if (! $asset instanceof Asset) {
            throw new RuntimeException((string) __('Für eine Gruppenanfrage muss ein Gerät gewählt werden.'));
        }
        if ((int) $asset->organization_id !== (int) $request->organization_id) {
            throw new RuntimeException((string) __('Das Gerät gehört nicht zu dieser Organisation.'));
        }

        // Überlappung → Hinweis statt stiller Doppelbuchung (Reservierungs-
        // Semantik der Verleihakte bleibt bei reserve() mit lockForUpdate).
        $conflict = $this->availability->findConflict($asset, $request->starts_at, $request->ends_at);
        if ($conflict !== null) {
            throw new RentalConflictException(
                (string) __(':asset ist von :from bis :to bereits belegt (:kind).', [
                    'asset' => $asset->name,
                    'from' => CarbonFmt::fdatetime($conflict->blockedFrom()),
                    'to' => CarbonFmt::fdatetime($conflict->blockedUntil()),
                    'kind' => $conflict->kind->label(),
                ]),
                $conflict,
            );
        }

        return DB::transaction(function () use ($request, $decider, $asset): RentalRequest {
            // Anfrage und Wohnung sperren: Annahme erzeugt genau eine bestätigte Belegung.
            Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $request = RentalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! $request->isOpen()) {
                throw new RuntimeException((string) __('Diese Anfrage ist bereits entschieden.'));
            }
            $organization = Organization::query()->withoutGlobalScopes()->findOrFail($request->organization_id);

            $case = $this->cases->open($organization, $decider, [
                'customer_id' => $request->customer_id,
                'starts_at' => $request->starts_at,
                'ends_at' => $request->ends_at,
                'responsible_user_id' => $decider->id,
                'rental_rate_card_id' => $asset->rentalProfile?->default_rate_card_id,
                'notes' => $request->note !== null
                    ? (string) __('Portal-Anfrage: :note', ['note' => $request->note])
                    : (string) __('Aus Portal-Anfrage angelegt.'),
            ], [$asset->id]);

            if ($asset->rentalProfile?->isHousing() === true) {
                $this->cases->reserve($case, $decider);
                $reservation = $case->reservations()->active()->firstOrFail();
            } else {
                $reservation = $this->availability->createWindow(
                $asset,
                RentalReservationKind::Soft,
                $request->starts_at,
                $request->ends_at,
                (string) __('Vormerkung aus Portal-Anfrage :number', ['number' => $case->number]),
                $decider->id,
                $case->id,
                );
            }

            $request->forceFill([
                'status' => RentalRequestStatus::Accepted->value,
                'asset_id' => $asset->id,
                'decided_by' => $decider->id,
                'decided_at' => Carbon::now(),
                'rental_reservation_id' => $reservation->id,
                'rental_case_id' => $case->id,
            ])->save();
            $request->audit('rental.requestAccepted', ['rental_case_id' => $case->id, 'reservation_id' => $reservation->id, 'asset_id' => $asset->id]);

            DB::afterCommit(fn () => $this->notifyDecision($request));

            return $request->refresh();
        });
    }

    public function decline(RentalRequest $request, User $decider, string $reason): RentalRequest {
        return DB::transaction(function () use ($request, $decider, $reason): RentalRequest {
            RentalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $request->refresh();
            if (! $request->isOpen()) {
                throw new RuntimeException((string) __('Diese Anfrage ist bereits entschieden.'));
            }

            $request->forceFill([
                'status' => RentalRequestStatus::Declined->value,
                'decided_by' => $decider->id,
                'decided_at' => Carbon::now(),
                'decline_reason' => trim($reason),
            ])->save();
            $request->audit('rental.requestDeclined', ['reason' => trim($reason)]);
            $this->notifyDecision($request);

            return $request;
        });
    }

    /** Rücknahme durch den Kunden — nur die eigene, noch offene Anfrage. */
    public function withdrawFromPortal(RentalRequest $request, User $portalUser): RentalRequest {
        return DB::transaction(function () use ($request, $portalUser): RentalRequest {
            RentalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $request->refresh();
            if ((int) $request->customer_id !== (int) $portalUser->customer_id) {
                throw new RuntimeException((string) __('Diese Anfrage gehört nicht zu Ihrem Zugang.'));
            }
            if (! $request->isOpen() && ! $request->isAwaitingPayment()) {
                throw new RuntimeException((string) __('Diese Anfrage lässt sich nicht mehr zurücknehmen.'));
            }

            $unpaid = $request->isAwaitingPayment();
            $request->forceFill(['status' => RentalRequestStatus::Withdrawn->value])->save();
            if ($unpaid) {
                $this->releaseCase($request, 'rental.prepayment.withdrawn_reason');
            }
            $request->audit('rental.requestWithdrawn', ['by_portal_user_id' => (int) $portalUser->id]);

            return $request;
        });
    }

    private function notifyRequested(RentalRequest $request, Customer $customer): void {
        $subject = $request->subjectLabel();
        $titleKey = $request->is_direct ? 'rental.portal.direct_notification' : 'Verleih-Anfrage von :customer';
        DB::afterCommit(function () use ($request, $customer, $subject, $titleKey): void {
            $this->notifier->notify(NotificationEvent::RentalRequested, $request, null, [
                'title' => (string) __($titleKey, ['customer' => $customer->name]),
                'title_key' => $titleKey,
                'title_params' => ['customer' => $customer->name],
                'message' => (string) __(':subject vom :from bis :to', ['subject' => $subject, 'from' => $request->starts_at->format('d.m.Y H:i'), 'to' => $request->ends_at->format('d.m.Y H:i')]),
                'message_key' => ':subject vom :from bis :to',
                'message_params' => ['subject' => $subject, 'from' => $request->starts_at->toIso8601String(), 'to' => $request->ends_at->toIso8601String()],
                'url' => route('rental.requests.index'),
            ]);
        });
    }

    /**
     * Entscheidungs-Mail an das Portalkonto. Fehlertolerant: Ein Mail-Fehler
     * darf die Entscheidung nie zurückrollen.
     */
    private function notifyDecision(RentalRequest $request): void {
        $portalUser = $request->portalUser;
        $email = $portalUser !== null ? trim((string) $portalUser->email) : '';
        if ($email === '') {
            return;
        }

        try {
            Mail::to($email)->send(new RentalRequestDecisionMail($request->fresh() ?? $request));
        } catch (\Throwable $e) {
            Log::warning('Verleih-Anfrage: Entscheidungs-Mail fehlgeschlagen.', [
                'rental_request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
