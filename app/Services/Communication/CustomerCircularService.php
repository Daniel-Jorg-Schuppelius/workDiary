<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerCircularService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Communication;

use App\Enums\Communication\CommunicationVisibility;
use App\Mail\CustomerCircularMail;
use App\Models\Communication\{CustomerCircular, CustomerCircularRecipient};
use App\Models\{Customer, User};
use App\Support\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Mail};
use RuntimeException;
use Throwable;

/**
 * Kundenrundschreiben (Feature 119, MVP-608).
 *
 * Der Versand ist ein **Geschäftsvorgang mit Nachweis**, kein Newsletter: Je
 * Empfänger entsteht eine Zeile — auch für Übersprungene. Die Mitteilung
 * landet zusätzlich als Kommunikationsnotiz in der Kundenakte, wo man sie
 * sucht, statt in einem Versand-Log daneben.
 */
class CustomerCircularService {
    /** Org-Einstellung „Versand braucht eine zweite Freigabe" (Feature 119). */
    public const APPROVAL_SETTING = 'communication.circular_approval';

    public function __construct(private readonly CommunicationNoteService $notes) {}

    /**
     * Empfängerkreis auflösen — über die BESTEHENDEN Kundenfilter, kein
     * zweiter Filtermechanismus.
     *
     * @param array<string, mixed> $filters
     * @return Collection<int, Customer>
     */
    public function audience(array $filters, bool $mandatory = false): Collection {
        $query = Customer::query()->whereNull('archived_at');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->search($search);
        }
        if (filled($filters['city'] ?? null)) {
            $query->where('address_city', $filters['city']);
        }
        if (filled($filters['zip_prefix'] ?? null)) {
            // whereLikeEscaped statt rohem LIKE: sonst wirkt ein `%` aus der
            // Eingabe als Platzhalter (App-Konvention).
            $query->whereLikeEscaped('address_zip', (string) $filters['zip_prefix'], 'prefix');
        }
        if (! empty($filters['with_active_projects'])) {
            $query->whereHas('projects', fn ($q) => $q->whereNull('archived_at'));
        }

        // Werbe-Opt-out gilt IMMER — außer bei Pflichtmitteilungen, und die
        // sind ausdrücklich als solche markiert.
        if (! $mandatory) {
            $query->where('no_bulk_mail', false);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Versand: erzeugt je Empfänger eine Nachweiszeile und eine
     * Kommunikationsnotiz. Idempotent über die Unique (circular, customer) —
     * ein zweiter Klick verschickt nicht doppelt.
     */
    public function send(CustomerCircular $circular, User $actor): CustomerCircular {
        if (! $circular->isDraft()) {
            throw new RuntimeException((string) __('circular.already_sent'));
        }
        if ($this->approvalRequired() && ! $circular->isApproved()) {
            throw new RuntimeException((string) __('circular.error.approval_missing'));
        }

        $recipients = $this->audience((array) ($circular->filters ?? []), (bool) $circular->is_mandatory);
        if ($recipients->isEmpty()) {
            throw new RuntimeException((string) __('circular.no_recipients'));
        }

        $circular->forceFill(['status' => CustomerCircular::STATUS_SENDING])->save();

        foreach ($recipients as $customer) {
            $this->sendTo($circular, $customer, $actor);
        }

        $circular->forceFill([
            'status' => CustomerCircular::STATUS_SENT,
            'sent_at' => CarbonImmutable::now(),
            'sent_by' => $actor->id,
        ])->save();

        $circular->audit('circular.sent', [
            'recipients' => $recipients->count(),
            'mandatory' => (bool) $circular->is_mandatory,
        ]);

        return $circular->refresh();
    }

    /**
     * Vier-Augen-Freigabe: Eine Mail an alle Kunden ist der Fall, in dem ein
     * zweites Paar Augen am meisten wert ist. Bewusst als Org-Einstellung mit
     * Default AUS — wer allein arbeitet, hätte sonst eine Sperre ohne Ausweg.
     */
    public function approvalRequired(): bool {
        return (bool) Setting::get(self::APPROVAL_SETTING, false);
    }

    /**
     * Freigabe erteilen. Die freigebende Person muss eine andere sein als die
     * anlegende — sonst wäre es keine Kontrolle, sondern ein zweiter Klick.
     */
    public function approve(CustomerCircular $circular, User $actor): CustomerCircular {
        if (! $circular->isDraft()) {
            throw new RuntimeException((string) __('circular.already_sent'));
        }
        if ((int) $circular->created_by === (int) $actor->id) {
            throw new RuntimeException((string) __('circular.error.approval_self'));
        }

        $circular->forceFill([
            'approved_by' => $actor->id,
            'approved_at' => CarbonImmutable::now(),
        ])->save();

        $circular->audit('circular.approved', ['by_user_id' => (int) $actor->id]);

        return $circular->refresh();
    }

    private function sendTo(CustomerCircular $circular, Customer $customer, User $actor): void {
        $email = $this->emailOf($customer);
        $body = $this->personalize((string) $circular->body, $customer);

        if ($email === '') {
            // Ohne Adresse ist der Kunde NICHT erreicht — das ist die
            // wichtigere Zeile als ein „versendet".
            $this->recordRecipient($circular, $customer, null, CustomerCircularRecipient::STATUS_SKIPPED, 'no_email');

            return;
        }

        try {
            Mail::to($email)->send(new CustomerCircularMail($circular, $customer, $body));
        } catch (Throwable $e) {
            $this->recordRecipient($circular, $customer, $email, CustomerCircularRecipient::STATUS_FAILED, class_basename($e));

            return;
        }

        $note = null;
        try {
            $note = $this->notes->create($customer, $actor, [
                'type' => 'email',
                'direction' => 'outbound',
                'occurred_at' => CarbonImmutable::now()->toDateTimeString(),
                'subject' => (string) $circular->subject,
                'body' => $body,
                // Nur ausdrücklich freigegebene Rundschreiben erscheinen im
                // Kundenportal — Default bleibt intern.
                'visibility' => $circular->portal_notice
                    ? CommunicationVisibility::Customer->value
                    : CommunicationVisibility::Internal->value,
            ]);
        } catch (Throwable) {
            // Der Nachweis in der Kundenakte ist wichtig, aber er darf den
            // bereits erfolgten Versand nicht als Fehlschlag erscheinen lassen.
        }

        $this->recordRecipient($circular, $customer, $email, CustomerCircularRecipient::STATUS_SENT, null, $note?->id);
    }

    private function recordRecipient(
        CustomerCircular $circular,
        Customer $customer,
        ?string $email,
        string $status,
        ?string $reason = null,
        ?int $noteId = null,
    ): void {
        DB::transaction(function () use ($circular, $customer, $email, $status, $reason, $noteId): void {
            CustomerCircularRecipient::query()->updateOrCreate(
                ['customer_circular_id' => $circular->id, 'customer_id' => $customer->id],
                [
                    'organization_id' => $circular->organization_id,
                    'email' => $email,
                    'status' => $status,
                    'reason' => $reason,
                    'sent_at' => $status === CustomerCircularRecipient::STATUS_SENT ? CarbonImmutable::now() : null,
                    'communication_note_id' => $noteId,
                ],
            );
        });
    }

    /**
     * Adresse des Kunden: eigenes Feld vor der Ansprechpartnerliste, dort der
     * als primär markierte Eintrag vor dem ersten mit Adresse.
     */
    private function emailOf(Customer $customer): string {
        $direct = trim((string) ($customer->email ?? ''));
        if ($direct !== '') {
            return $direct;
        }

        $persons = collect((array) ($customer->contact_persons ?? []))
            ->filter(fn (array $person): bool => filled($person['email'] ?? null));

        $primary = $persons->first(fn (array $person): bool => ! empty($person['primary'])) ?? $persons->first();

        return trim((string) ($primary['email'] ?? ''));
    }

    /** Platzhalter der Anrede — bewusst wenige und ohne Fallback-Erfindung. */
    private function personalize(string $body, Customer $customer): string {
        return strtr($body, [
            ':firma' => (string) ($customer->company ?? $customer->name ?? ''),
            ':kunde' => (string) ($customer->name ?? ''),
            ':ansprechpartner' => (string) ($customer->contact_name ?? ''),
        ]);
    }
}
