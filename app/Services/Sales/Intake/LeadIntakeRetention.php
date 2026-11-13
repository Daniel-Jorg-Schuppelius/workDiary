<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadIntakeRetention.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake;

use App\Enums\Sales\LeadIntakeDecision;
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Models\Sales\{Lead, LeadIntake};
use App\Services\Sales\Intake\Dto\IntakeRetentionRule;
use Carbon\{CarbonImmutable, CarbonInterface};

/**
 * Aufbewahrung der Aufnahmeakte (Feature 200, MVP-1643) über den vorhandenen
 * Aufbewahrungsmechanismus — kein eigener Löschlauf:
 *
 * - Die Regel kommt aus dem eingefrorenen Paket (GwG: fünf Jahre, § 8 Abs. 4
 *   GwG); gilt mehr als eine, die längste.
 * - Anker: angenommen → Ende der Geschäftsbeziehung (Datum am Kunden,
 *   § 8 Abs. 4 Satz 2 GwG; solange es fehlt, läuft die Frist nicht an);
 *   abgelehnt → Tag der Ablehnung; offen oder abgebrochen → letzter Stand der
 *   Angaben (Satz 3: Jahr, in dem die Angabe festgestellt wurde).
 * - Die Anonymisierung des Leads (Löschbereich `leads`) lässt Akten mit
 *   laufender Frist stehen und löscht die übrigen mit; der Löschbereich
 *   `lead_intakes` schlägt Akten nach Fristende zur Löschung vor (zweistufige
 *   Bestätigung, Legal Hold am Kunden sperrt). Archivieren oder Löschen des
 *   Kunden berührt die Akte nicht (sie hängt am Lead); ist der Kunde gelöscht,
 *   gilt die Geschäftsbeziehung als beendet, sobald der Lauf das bemerkt.
 */
final class LeadIntakeRetention {
    /** Längste Aufbewahrungsregel der Pakete mit erfassten Angaben; null = keine. */
    public function ruleFor(LeadIntake $intake): ?IntakeRetentionRule {
        $values = $intake->fieldDocumentValues();
        $schema = $intake->fullSchema();
        $rule = null;
        foreach ($intake->packageSnapshots() as $package) {
            if ($package->retention === null || ($rule !== null && $rule->years >= $package->retention->years)) {
                continue;
            }
            // Ohne erfasste Angabe gibt es nichts aufzubewahren.
            $answered = false;
            foreach ($schema as $field) {
                if ($field->type->hasValue() && IntakeKeys::packageOf($field->key) === $package->key && $values->isAnswered($field)) {
                    $answered = true;
                    break;
                }
            }
            if ($answered) {
                $rule = $package->retention;
            }
        }

        return $rule;
    }

    /** Hält die Akte über die Anonymisierung des Leads hinaus? */
    public function retains(LeadIntake $intake): bool {
        return $this->ruleFor($intake) !== null;
    }

    /** Letzter Tag der Aufbewahrung; null = keine Regel oder Frist läuft noch nicht an. */
    public function untilFor(LeadIntake $intake): ?CarbonImmutable {
        $rule = $this->ruleFor($intake);
        $anchor = $rule !== null ? $this->anchor($intake) : null;

        return $rule !== null && $anchor !== null ? $rule->until($anchor) : null;
    }

    /** Frist neu berechnen und still speichern (kein Änderungsprotokoll je Lauf). */
    public function refresh(LeadIntake $intake): void {
        $until = $this->untilFor($intake)?->toDateString();
        if ($intake->retention_until?->toDateString() !== $until) {
            $intake->forceFill(['retention_until' => $until])->saveQuietly();
        }
    }

    /**
     * Fristen einer Organisation nachführen — vor jeder Auswahl des
     * Löschbereichs `lead_intakes`.
     *
     * @return int Anzahl geänderter Fristen
     */
    public function refreshOrganization(Organization $organization): int {
        $today = CarbonImmutable::today()->toDateString();
        $changed = 0;
        $intakes = LeadIntake::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNotNull('packages')
            ->get();
        foreach ($intakes as $intake) {
            $before = $intake->retention_until?->toDateString();
            if ($intake->decision === LeadIntakeDecision::Accepted && $intake->relationship_ended_on === null) {
                $customer = $intake->customer_id !== null ? Customer::query()->withoutGlobalScopes()->find($intake->customer_id) : null;
                $ended = $customer?->relationship_ended_on;
                if ($ended !== null) {
                    $intake->forceFill(['relationship_ended_on' => $ended->toDateString()])->saveQuietly();
                } elseif ($customer === null && $intake->completed_at !== null) {
                    // Kunde gelöscht: die Geschäftsbeziehung endete spätestens heute.
                    $intake->forceFill(['relationship_ended_on' => $today])->saveQuietly();
                }
            }
            $this->refresh($intake);
            if ($intake->retention_until?->toDateString() !== $before) {
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Teil der Anonymisierung eines Leads: Akten ohne Aufbewahrung oder nach
     * Fristende gehen mit, Akten mit laufender Frist bleiben (Art. 6 Abs. 1
     * lit. c DSGVO in Verbindung mit der Regel des Pakets).
     */
    public function onLeadAnonymized(Lead $lead): void {
        $today = CarbonImmutable::today();
        $intakes = LeadIntake::query()->withoutGlobalScopes()->where('lead_id', $lead->id)->orderByDesc('revision')->get();
        foreach ($intakes as $intake) {
            $this->refresh($intake);
            if (! $this->retains($intake) || ($intake->retention_until !== null && $intake->retention_until->lt($today))) {
                $this->purge($intake);
            }
        }
    }

    /** Löschen nach Fristende (Anhänge räumt HasAttachments ab). */
    public function purge(LeadIntake $intake): void {
        $intake->delete();
    }

    private function anchor(LeadIntake $intake): ?CarbonInterface {
        return match ($intake->decision) {
            LeadIntakeDecision::Accepted => $intake->relationship_ended_on ?? $this->customerEnd($intake),
            LeadIntakeDecision::Rejected => $intake->decided_at,
            null => $intake->updated_at ?? $intake->created_at,
        };
    }

    private function customerEnd(LeadIntake $intake): ?CarbonInterface {
        if ($intake->customer_id === null) {
            return null;
        }

        return Customer::query()->withoutGlobalScopes()->whereKey($intake->customer_id)->first()?->relationship_ended_on;
    }
}
