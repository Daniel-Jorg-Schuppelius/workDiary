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
use App\Models\{Asset, Customer, Organization, User};
use App\Models\Rental\{RentalProfile, RentalRequest};
use App\Services\Notification\NotificationDispatcher;
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
    public function __construct(
        private readonly RentalAvailabilityService $availability,
        private readonly RentalCaseService $cases,
        private readonly NotificationDispatcher $notifier,
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
    public function requestFromPortal(Customer $customer, User $portalUser, ?Asset $asset, ?string $groupCode, Carbon $from, Carbon $to, ?string $note = null): RentalRequest {
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
        if ($asset === null && ! $bookable->contains(fn (RentalProfile $p): bool => $p->group_code === $groupCode)) {
            throw new RuntimeException((string) __('Diese Gerätegruppe ist im Portal nicht anfragbar.'));
        }

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
        ]);
        $request->audit('rental.requested', ['asset_id' => $asset?->id, 'group_code' => $request->group_code, 'by_portal_user_id' => (int) $portalUser->id]);

        $this->notifyRequested($request, $customer);

        return $request;
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
                    'from' => $conflict->blockedFrom()->format('d.m.Y H:i'),
                    'to' => $conflict->blockedUntil()->format('d.m.Y H:i'),
                    'kind' => $conflict->kind->label(),
                ]),
                $conflict,
            );
        }

        return DB::transaction(function () use ($request, $decider, $asset): RentalRequest {
            $organization = Organization::query()->withoutGlobalScopes()->findOrFail($request->organization_id);

            $case = $this->cases->open($organization, $decider, [
                'customer_id' => $request->customer_id,
                'starts_at' => $request->starts_at,
                'ends_at' => $request->ends_at,
                'responsible_user_id' => $decider->id,
                'notes' => $request->note !== null
                    ? (string) __('Portal-Anfrage: :note', ['note' => $request->note])
                    : (string) __('Aus Portal-Anfrage angelegt.'),
            ], [$asset->id]);

            $reservation = $this->availability->createWindow(
                $asset,
                RentalReservationKind::Soft,
                $request->starts_at,
                $request->ends_at,
                (string) __('Vormerkung aus Portal-Anfrage :number', ['number' => $case->number]),
                $decider->id,
                $case->id,
            );

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
    }

    /** Rücknahme durch den Kunden — nur die eigene, noch offene Anfrage. */
    public function withdrawFromPortal(RentalRequest $request, User $portalUser): RentalRequest {
        if ((int) $request->customer_id !== (int) $portalUser->customer_id) {
            throw new RuntimeException((string) __('Diese Anfrage gehört nicht zu Ihrem Zugang.'));
        }
        if (! $request->isOpen()) {
            throw new RuntimeException((string) __('Diese Anfrage lässt sich nicht mehr zurücknehmen.'));
        }

        $request->forceFill(['status' => RentalRequestStatus::Withdrawn->value])->save();
        $request->audit('rental.requestWithdrawn', ['by_portal_user_id' => (int) $portalUser->id]);

        return $request;
    }

    private function notifyRequested(RentalRequest $request, Customer $customer): void {
        $subject = $request->subjectLabel();
        DB::afterCommit(function () use ($request, $customer, $subject): void {
            $this->notifier->notify(NotificationEvent::RentalRequested, $request, null, [
                'title' => (string) __('Verleih-Anfrage von :customer', ['customer' => $customer->name]),
                'title_key' => 'Verleih-Anfrage von :customer',
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
