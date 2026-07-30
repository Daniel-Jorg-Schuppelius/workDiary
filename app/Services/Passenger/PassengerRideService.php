<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PassengerRideService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Passenger;

use App\Enums\Diary\Status;
use App\Enums\Passenger\{RideOperationMode, RideOrderChannel, RidePriceKind, RideStatus};
use App\Models\Passenger\{PassengerConcession, PassengerFareTariff, PassengerRide, PassengerVehicleProfile};
use App\Models\{DiaryEntry, Organization, User, Vehicle};
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fahrtlebenszyklus der Personenbeförderung (MVP-456): Annahme →
 * Disposition → Fahrt → Abschluss mit den Pflichtgates aus Konzept §6.
 *
 * Grundsätze:
 *  - Die Betriebsart wird bei Annahme eingefroren; ein stiller Wechsel ist
 *    ausgeschlossen (Korrektur = neuer Fahrtauftrag, Konzept §3).
 *  - Disposition verlangt gültige Konzession, fahrgastbeförderungsberechtigten
 *    Fahrer und ein geeignetes Fahrzeug mit gültigen Nachweisen; der Snapshot
 *    (Fahrer/Fahrzeug/Konzession/Gerät) bleibt an der Fahrt erhalten.
 *  - Tarif-/Festpreis wird VOR Fahrtbeginn eingefroren; der tatsächliche
 *    Gerätewert bleibt getrennt (Konzept §8).
 *  - Jede Fahrt hängt an einem {@see DiaryEntry} als Fall-/Timeline-Anker.
 */
class PassengerRideService {
    /** Name der Pflicht-Qualifikation (Seed im Branchenprofil). */
    public const DRIVER_QUALIFICATION = 'Fahrerlaubnis zur Fahrgastbeförderung (P-Schein)';

    /**
     * Fahrtannahme (Gate „Fahrtannahme", Konzept §6).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function accept(Organization $organization, User $actor, array $attributes): PassengerRide {
        $mode = RideOperationMode::from((string) $attributes['operation_mode']);
        $channel = RideOrderChannel::from((string) $attributes['order_channel']);

        // Ziel ODER zulässige Zielfreiheit (nur Taxe kennt offene Ziele).
        $destination = trim((string) ($attributes['destination_address'] ?? ''));
        $destinationOpen = (bool) ($attributes['destination_open'] ?? false);
        if ($destination === '' && ! $destinationOpen) {
            throw ValidationException::withMessages(['destination_address' => (string) __('passenger.error.destination_required')]);
        }

        // Mietwagen/Bedarfsverkehr: Eingangsnachweis am Betriebssitz (§ 49 IV).
        if ($mode->requiresOrderReceipt() && ! $channel->isOfficeReceipt()) {
            throw ValidationException::withMessages(['order_channel' => (string) __('passenger.error.receipt_channel_invalid')]);
        }

        if (trim((string) ($attributes['pickup_address'] ?? '')) === '') {
            throw ValidationException::withMessages(['pickup_address' => (string) __('passenger.error.pickup_required')]);
        }

        return DB::transaction(function () use ($organization, $actor, $attributes, $mode, $channel, $destination, $destinationOpen): PassengerRide {
            $entry = DiaryEntry::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $actor->id,
                'customer_id' => $attributes['customer_id'] ?? null,
                'title' => (string) __('passenger.entry_title', ['mode' => $mode->label()]),
                'content' => (string) __('passenger.entry_content', ['channel' => $channel->label()]),
                'status' => Status::Open,
                'start_at' => now(),
                'end_at' => now()->addHour(),
            ]);

            $ride = PassengerRide::query()->create([
                'organization_id' => $organization->id,
                'diary_entry_id' => $entry->id,
                'operation_mode' => $mode,
                'order_channel' => $channel,
                'status' => RideStatus::Accepted,
                'mediator_reference' => $attributes['mediator_reference'] ?? null,
                'mediator_plugin' => $attributes['mediator_plugin'] ?? null,
                'requested_at' => $attributes['requested_at'] ?? now(),
                'accepted_at' => now(),
                'accepted_by' => $actor->id,
                'pickup_address' => trim((string) ($attributes['pickup_address'] ?? '')) ?: null,
                'destination_address' => $destination !== '' ? $destination : null,
                'destination_open' => $destinationOpen,
                'waypoints' => $attributes['waypoints'] ?? null,
                'window_start' => $attributes['window_start'] ?? null,
                'window_end' => $attributes['window_end'] ?? null,
                'passenger_count' => (int) ($attributes['passenger_count'] ?? 1),
                'luggage_count' => (int) ($attributes['luggage_count'] ?? 0),
                'child_seats' => (int) ($attributes['child_seats'] ?? 0),
                'wheelchair' => (bool) ($attributes['wheelchair'] ?? false),
                'animal' => (bool) ($attributes['animal'] ?? false),
                'barrier_free_required' => (bool) ($attributes['barrier_free_required'] ?? false),
                'passenger_name' => trim((string) ($attributes['passenger_name'] ?? '')) ?: null,
                'passenger_contact' => trim((string) ($attributes['passenger_contact'] ?? '')) ?: null,
                'order_received_at' => $mode->requiresOrderReceipt() ? now() : null,
                'order_receipt_reference' => $attributes['order_receipt_reference'] ?? null,
                'created_by' => $actor->id,
            ]);

            $ride->audit('passenger.ride_accepted', [
                'operation_mode' => $mode->value,
                'order_channel' => $channel->value,
            ]);

            return $ride;
        });
    }

    /**
     * Disposition (Gate „Disposition", Konzept §6): Fahrer, Fahrzeug und
     * Konzession werden geprüft und als unveränderlicher Snapshot verankert.
     */
    public function assign(PassengerRide $ride, User $driver, Vehicle $vehicle, User $actor): PassengerRide {
        $this->assertTransition($ride, RideStatus::Assigned);

        $issues = $this->dispatchIssues($ride, $driver, $vehicle);
        if ($issues !== []) {
            throw ValidationException::withMessages(['assignment' => array_map(static fn(string $key): string => (string) __($key), $issues)]);
        }

        $concession = PassengerConcession::query()
            ->where('organization_id', $ride->organization_id)
            ->validFor($ride->operation_mode)
            ->orderBy('id')
            ->firstOrFail();
        $profile = PassengerVehicleProfile::query()->where('vehicle_id', $vehicle->id)->first();

        $ride->forceFill([
            'status' => RideStatus::Assigned,
            'assigned_at' => now(),
            'driver_user_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'concession_id' => $concession->id,
            'assignment_snapshot' => [
                'driver' => ['id' => $driver->id, 'name' => (string) $driver->name],
                'vehicle' => [
                    'id' => $vehicle->id,
                    'license_plate' => (string) $vehicle->license_plate,
                    'order_number' => $profile?->order_number,
                    'meter_kind' => $profile?->meter_kind,
                    'meter_serial' => $profile?->meter_serial,
                    'tse_reference' => $profile?->tse_reference,
                ],
                'concession' => [
                    'id' => $concession->id,
                    'authority' => $concession->authority,
                    'reference_no' => $concession->reference_no,
                    'tariff_area' => $concession->tariff_area,
                ],
                'assigned_by' => $actor->id,
                'assigned_at' => now()->toIso8601String(),
            ],
        ])->save();
        $ride->audit('passenger.ride_assigned', ['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id]);

        return $ride;
    }

    /**
     * Dispositions-Hindernisse (leer = disponierbar). Rückgabe sind
     * Übersetzungs-Keys — der Aufrufer lokalisiert.
     *
     * @return list<string>
     */
    public function dispatchIssues(PassengerRide $ride, User $driver, Vehicle $vehicle): array {
        $issues = [];

        // Fahrerlaubnis zur Fahrgastbeförderung: befristete Qualifikation.
        $today = now()->startOfDay();
        $qualified = $driver->qualifications()
            ->where('name', self::DRIVER_QUALIFICATION)
            ->wherePivot('valid_until', '>=', $today->toDateString())
            ->exists();
        if (! $qualified) {
            $issues[] = 'passenger.issue.driver_unqualified';
        }

        // Konzession der Betriebsart muss am Fahrttag gültig sein.
        $hasConcession = PassengerConcession::query()
            ->where('organization_id', $ride->organization_id)
            ->validFor($ride->operation_mode)
            ->exists();
        if (! $hasConcession) {
            $issues[] = 'passenger.issue.concession_missing';
        }

        // Fahrzeugprofil: Betriebsart zugelassen, Nachweise gültig, Anforderungen erfüllt.
        $profile = PassengerVehicleProfile::query()->where('vehicle_id', $vehicle->id)->first();
        if ($profile === null) {
            $issues[] = 'passenger.issue.vehicle_profile_missing';
        } else {
            if (! $profile->supports($ride->operation_mode)) {
                $issues[] = 'passenger.issue.vehicle_mode_unsupported';
            }
            if ($profile->expiredProofs() !== []) {
                $issues[] = 'passenger.issue.vehicle_proofs_expired';
            }
            if ($ride->barrier_free_required && ! $profile->barrier_free) {
                $issues[] = 'passenger.issue.vehicle_not_barrier_free';
            }
            if ($ride->wheelchair && $profile->wheelchair_places < 1) {
                $issues[] = 'passenger.issue.vehicle_no_wheelchair_place';
            }
            if ($profile->passenger_seats !== null && $ride->passenger_count > $profile->passenger_seats) {
                $issues[] = 'passenger.issue.vehicle_too_small';
            }
        }

        return $issues;
    }

    /**
     * Fahrtbeginn (Gate „Fahrtbeginn"): Tarif/Festpreis wird eingefroren.
     *
     * @param  array{price_kind: string, tariff?: PassengerFareTariff|null, planned_net?: string|null, estimated_km?: string|null, estimated_minutes?: int|null}  $pricing
     */
    public function start(PassengerRide $ride, array $pricing, User $actor): PassengerRide {
        $this->assertTransition($ride, RideStatus::EnRoutePickup);
        if ($ride->driver_user_id === null || $ride->vehicle_id === null || $ride->assignment_snapshot === null) {
            throw ValidationException::withMessages(['assignment' => (string) __('passenger.error.not_assigned')]);
        }

        $kind = RidePriceKind::from((string) $pricing['price_kind']);
        $tariff = $pricing['tariff'] ?? null;
        $plannedNet = $pricing['planned_net'] ?? null;

        if ($ride->operation_mode->requiresRegulatedTariff() && $kind === RidePriceKind::Tariff && ! $tariff instanceof PassengerFareTariff) {
            throw ValidationException::withMessages(['tariff' => (string) __('passenger.error.tariff_required')]);
        }

        $snapshot = null;
        if ($tariff instanceof PassengerFareTariff) {
            $snapshot = $tariff->snapshot();
            if ($plannedNet === null && isset($pricing['estimated_km'])) {
                $plannedNet = $tariff->calculate((string) $pricing['estimated_km'], (int) (($pricing['estimated_minutes'] ?? 0) * 60));
            }
            // Festpreis nur im behördlich zulässigen Korridor (§ 51 IV PBefG).
            if ($kind === RidePriceKind::FixedPrice && $plannedNet !== null && isset($pricing['estimated_km'])) {
                $tariffPrice = $tariff->calculate((string) $pricing['estimated_km'], (int) (($pricing['estimated_minutes'] ?? 0) * 60));
                if (! $tariff->fixedPriceWithinCorridor((string) $plannedNet, $tariffPrice)) {
                    throw ValidationException::withMessages(['planned_net' => (string) __('passenger.error.fixed_price_outside_corridor')]);
                }
            }
        }

        $ride->forceFill([
            'status' => RideStatus::EnRoutePickup,
            'pickup_started_at' => now(),
            'price_kind' => $kind,
            'tariff_id' => $tariff?->id,
            'fare_snapshot' => $snapshot,
            'planned_net' => $plannedNet,
            // Ohne Tarif (Vertrags-/Festpreis) bleibt die Fahrtwährung; der
            // DB-Default greift erst beim Insert, daher expliziter Fallback.
            'currency' => $tariff?->currency->value
                ?? ($ride->getAttribute('currency') instanceof CurrencyCode
                    ? $ride->currency->value
                    : CurrencyCode::Euro->value),
        ])->save();
        $ride->audit('passenger.ride_started', ['price_kind' => $kind->value, 'by' => $actor->id]);

        return $ride;
    }

    /** Statuswechsel entlang der erlaubten Pfade (wartend/besetzt). */
    public function transition(PassengerRide $ride, RideStatus $target, User $actor): PassengerRide {
        $this->assertTransition($ride, $target);

        $timestamps = match ($target) {
            RideStatus::Waiting => ['waiting_started_at' => now()],
            RideStatus::Occupied => ['picked_up_at' => now()],
            default => [],
        };
        $ride->forceFill(['status' => $target, ...$timestamps])->save();
        $ride->audit('passenger.ride_transition', ['to' => $target->value, 'by' => $actor->id]);

        return $ride;
    }

    /**
     * Fahrtabschluss (Gate „Fahrtabschluss"): Strecke/Gerätebezug, Steuer-
     * entscheidung und Zahlungsart sind Pflicht; der Gerätewert bleibt vom
     * geplanten Preis getrennt.
     *
     * @param  array<string, mixed>  $closing
     */
    public function complete(PassengerRide $ride, array $closing, User $actor): PassengerRide {
        $this->assertTransition($ride, RideStatus::Completed);

        $meterNet = trim((string) ($closing['meter_net'] ?? ''));
        $taxRate = $closing['tax_rate'] ?? null;
        $payment = trim((string) ($closing['payment_method'] ?? ''));
        if ($meterNet === '' || ! is_numeric($meterNet)) {
            throw ValidationException::withMessages(['meter_net' => (string) __('passenger.error.meter_value_required')]);
        }
        if ($taxRate === null || ! is_numeric((string) $taxRate)) {
            throw ValidationException::withMessages(['tax_rate' => (string) __('passenger.error.tax_decision_required')]);
        }
        if ($payment === '') {
            throw ValidationException::withMessages(['payment_method' => (string) __('passenger.error.payment_required')]);
        }

        $taxAmount = bcdiv(bcmul($meterNet, (string) $taxRate, 6), '100', 2);
        $ride->forceFill([
            'status' => RideStatus::Completed,
            'completed_at' => now(),
            'odometer_end_km' => $closing['odometer_end_km'] ?? $ride->odometer_end_km,
            'occupied_km' => $closing['occupied_km'] ?? $ride->occupied_km,
            'empty_km' => $closing['empty_km'] ?? $ride->empty_km,
            'waiting_seconds' => (int) ($closing['waiting_seconds'] ?? $ride->waiting_seconds),
            'meter_net' => $meterNet,
            'tax_rate' => (string) $taxRate,
            'tax_amount' => $taxAmount,
            'gross_amount' => bcadd($meterNet, $taxAmount, 2),
            'tax_context' => $closing['tax_context'] ?? $ride->tax_context,
            'payment_method' => $payment,
        ])->save();

        $ride->diaryEntry?->forceFill(['status' => Status::Done, 'end_at' => now()])->save();
        $ride->audit('passenger.ride_completed', [
            'meter_net' => $meterNet,
            'deviation' => $ride->refresh()->fareDeviation(),
            'by' => $actor->id,
        ]);

        return $ride;
    }

    /** Storno/No-show/Abbruch entlang der zulässigen Nebenpfade. */
    public function close(PassengerRide $ride, RideStatus $target, string $reason, User $actor, ?string $note = null): PassengerRide {
        if (! in_array($target, [RideStatus::Cancelled, RideStatus::NoShow, RideStatus::Aborted], true)) {
            throw ValidationException::withMessages(['status' => (string) __('passenger.error.invalid_transition')]);
        }
        $this->assertTransition($ride, $target);

        $ride->forceFill([
            'status' => $target,
            'cancelled_at' => now(),
            'closing_reason' => $reason,
            'closing_note' => $note,
        ])->save();
        $ride->diaryEntry?->forceFill(['status' => Status::Cancelled])->save();
        $ride->audit('passenger.ride_closed', ['status' => $target->value, 'reason' => $reason, 'by' => $actor->id]);

        return $ride;
    }

    /**
     * Mietwagen-Rückkehrnachweis bzw. Folgeauftrag (§ 49 Abs. 4 PBefG).
     */
    public function recordReturn(PassengerRide $ride, User $actor, ?PassengerRide $followUp = null): PassengerRide {
        if (! $ride->operation_mode->requiresReturnToBase()) {
            throw ValidationException::withMessages(['operation_mode' => (string) __('passenger.error.return_not_applicable')]);
        }

        $ride->forceFill([
            'returned_to_base_at' => $followUp === null ? now() : null,
            'follow_up_ride_id' => $followUp?->id,
        ])->save();
        $ride->audit('passenger.ride_return_recorded', [
            'follow_up_ride_id' => $followUp?->id,
            'by' => $actor->id,
        ]);

        return $ride;
    }

    private function assertTransition(PassengerRide $ride, RideStatus $target): void {
        if (! $ride->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => (string) __('passenger.error.invalid_transition_detail', [
                    'from' => $ride->status->value,
                    'to' => $target->value,
                ]),
            ]);
        }
    }
}
