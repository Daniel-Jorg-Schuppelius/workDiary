<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\Sales\LeadStatus;
use App\Models\{Customer, Lead, User};
use Illuminate\Support\{Carbon, Collection};
use RuntimeException;

/**
 * Lead-Pipeline (Feature 091, MVP-654/655): Statusführung, Dublettenprüfung
 * und Konvertierung.
 *
 * Zwei Festlegungen tragen die Konvertierung:
 *
 * 1. **Dublettenprüfung vor der Anlage, nicht danach.** Ein zweiter
 *    Kundenstamm durch die Hintertür ist genau das, was die Merge-Services
 *    hinterher mühsam aufräumen — die Kandidaten werden VOR dem Anlegen
 *    gezeigt, und die Verbindung mit einem Bestandskunden ist gleichwertige
 *    Option, nicht Ausnahme.
 * 2. **Die Lead-Akte bleibt.** Notizen werden nicht auf den Kunden
 *    umgehängt — das verfälschte die Historie („wer wusste wann was" gehört
 *    zur Akquise-Akte). Kunde und Lead verweisen aufeinander.
 */
class LeadService {
    public function transition(Lead $lead, LeadStatus $to, ?string $reason = null): Lead {
        if (! in_array($to, $lead->status->next(), true)) {
            throw new RuntimeException((string) __('Der Statuswechsel von :from nach :to ist nicht vorgesehen.', [
                'from' => $lead->status->label(),
                'to' => $to->label(),
            ]));
        }

        $lead->forceFill([
            'status' => $to,
            'discard_reason' => $to === LeadStatus::Discarded ? $reason : null,
            'last_contact_at' => Carbon::now(),
        ])->save();

        $lead->audit('lead.status_changed', ['to' => $to->value, 'reason' => $reason]);

        return $lead;
    }

    /**
     * Mögliche Bestandskunden-Dubletten für diesen Lead.
     *
     * Bewusst schmale Heuristik (Name + E-Mail) statt des vollen
     * EntityMatcher-Profils: Hier geht es um eine Warnliste vor der Anlage,
     * nicht um Bestandsbereinigung.
     *
     * @return Collection<int, Customer>
     */
    public function duplicateCandidates(Lead $lead): Collection {
        $query = Customer::query()->limit(5);

        $terms = array_values(array_filter([
            trim((string) $lead->company),
            trim((string) $lead->contact_name),
        ], static fn (string $t): bool => mb_strlen($t) >= 3));
        $email = trim((string) $lead->email);

        if ($terms === [] && $email === '') {
            return new Collection;
        }

        $query->where(function ($q) use ($terms, $email): void {
            foreach ($terms as $term) {
                $q->orWhereLikeEscaped('name', $term);
            }
            if ($email !== '') {
                $q->orWhere('email', $email);
            }
        });

        return new Collection($query->orderBy('name')->get()->all());
    }

    /**
     * Konvertiert den Lead: verbindet ihn mit einem Bestandskunden ODER legt
     * einen neuen an. Beides setzt den Endzustand `converted`.
     */
    public function convert(Lead $lead, User $actor, ?Customer $existing = null): Customer {
        if ($lead->status === LeadStatus::Converted) {
            throw new RuntimeException((string) __('Dieser Lead ist bereits konvertiert.'));
        }
        if ($lead->status === LeadStatus::Discarded) {
            throw new RuntimeException((string) __('Ein verworfener Lead wird erst requalifiziert, dann konvertiert.'));
        }

        $customer = $existing ?? Customer::query()->create([
            'organization_id' => $lead->organization_id,
            'name' => $lead->displayName(),
            'contact_person' => $lead->contact_name,
            'email' => $lead->email,
            'phone' => $lead->phone,
        ]);

        $lead->forceFill([
            'status' => LeadStatus::Converted,
            'customer_id' => $customer->id,
            'last_contact_at' => Carbon::now(),
        ])->save();

        $lead->audit('lead.converted', [
            'customer_id' => $customer->id,
            'existing' => $existing !== null,
        ]);

        return $customer;
    }

    /**
     * Anonymisiert einen Lead (Retention-Purge): PII verschwindet, die
     * Kennzahl (Quelle, Status, Zeitraum) bleibt für die Pipeline-Statistik.
     * Auch die Notizen der Akte werden geleert — sie sind Teil der PII.
     */
    public function anonymize(Lead $lead): void {
        $lead->communicationNotes()->update(['subject' => (string) __('Anonymisiert'), 'body' => null]);
        $lead->forceFill([
            'company' => null,
            'contact_name' => null,
            'email' => null,
            'phone' => null,
            'interest' => null,
            'anonymized_at' => Carbon::now(),
        ])->save();
    }
}
