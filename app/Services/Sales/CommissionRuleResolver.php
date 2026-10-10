<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionRuleResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\Sales\{CommissionAssignmentSource, CommissionScope};
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Sales\{CommissionAgent, CommissionRule, Lead};
use App\Services\Sales\Commission\CommissionDocuments;
use App\Services\Sales\Contracts\CommissionDocument;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Carbon;

/**
 * Zwei Fragen, eine Klasse (Feature 146, MVP-729):
 *
 *  1. **Wem** gehoert der Beleg? Standard ist die Herkunft aus der
 *     Lead-Pipeline (Feature 091: der konvertierte Lead traegt den
 *     Verantwortlichen); die Vertriebsperson am Beleg schlaegt sie als
 *     manuelle Zuordnung. Beleg = lokale Rechnung oder gespiegelte Rechnung
 *     des Buchhaltungsprogramms ({@see CommissionDocument}, MVP-1119).
 *  2. **Welche** Regel gilt? Genau eine: hoechste Prioritaet, bei Gleichstand
 *     der engere Geltungsbereich, zuletzt die juengere Regel. Es wird nichts
 *     summiert — zwei Regeln ergeben nie zwei Provisionen.
 *
 * Die Klasse schreibt nichts. Sie wird sowohl vom Auslöser
 * ({@see CommissionAccrualService}) als auch von der Vorschau in der UI
 * benutzt, damit beide dieselbe Antwort geben.
 */
class CommissionRuleResolver {
    public function __construct(private readonly CommissionDocuments $documents) {}

    /**
     * Zuordnung Beleg → Vertriebsperson. `null` = niemand zustaendig, also
     * keine Provision (das ist der Normalfall ohne Vertriebsorganisation).
     */
    public function assignmentFor(Invoice|CommissionDocument $subject): ?CommissionAssignment {
        $document = $this->documents->of($subject);
        $manual = $document->salesUserId() !== null
            ? User::query()->whereKey($document->salesUserId())->first()
            : null;

        $lead = $this->originLead($document);

        if ($manual instanceof User) {
            return new CommissionAssignment($manual, CommissionAssignmentSource::Manual, $lead);
        }

        $agent = $document->salesAgentId() !== null ? CommissionAgent::query()->whereKey($document->salesAgentId())->first() : null;
        if ($agent instanceof CommissionAgent) {
            return new CommissionAssignment(null, CommissionAssignmentSource::Manual, $lead, $agent);
        }

        if ($lead !== null && $lead->responsible_user_id !== null) {
            $responsible = User::query()->whereKey($lead->responsible_user_id)->first();
            if ($responsible instanceof User) {
                return new CommissionAssignment($responsible, CommissionAssignmentSource::Lead, $lead);
            }
        }

        return null;
    }

    public function originLead(Invoice|CommissionDocument $subject): ?Lead {
        $document = $this->documents->of($subject);
        if ($document->customerId() === null) {
            return null;
        }

        return Lead::query()
            ->where('organization_id', $document->organizationId())
            ->where('customer_id', $document->customerId())
            ->whereNotNull('responsible_user_id')
            ->orderByDesc('id')
            ->first();
    }

    public function ruleFor(Invoice|CommissionDocument $subject, CommissionAssignment $assignment, Carbon $on): ?CommissionRule {
        $document = $this->documents->of($subject);
        $candidates = CommissionRule::query()
            ->where('organization_id', $document->organizationId())
            ->validOn($on)
            ->get()
            ->filter(fn (CommissionRule $rule): bool => $this->applies($rule, $document, $assignment))
            ->sortByDesc(fn (CommissionRule $rule): string => $rule->selectionKey());

        return $candidates->first();
    }

    public function baseAmountFor(Invoice|CommissionDocument $subject, CommissionRule $rule): Money {
        $document = $this->documents->of($subject);
        if ($rule->scope !== CommissionScope::ProductGroup) {
            return $document->netAmount();
        }

        return $document->netOfCategory((string) $rule->scope_value) ?? Money::zero($document->currency());
    }

    private function applies(CommissionRule $rule, CommissionDocument $document, CommissionAssignment $assignment): bool {
        return match ($rule->scope) {
            CommissionScope::All => true,
            CommissionScope::User => $rule->user_id !== null && $assignment->user !== null && (int) $rule->user_id === (int) $assignment->user->id,
            CommissionScope::Agent => $rule->commission_agent_id !== null && $assignment->agent !== null && (int) $rule->commission_agent_id === (int) $assignment->agent->id,
            CommissionScope::LeadSource => $assignment->lead !== null
                && $rule->scope_value !== null
                && $assignment->lead->source->value === $rule->scope_value,
            CommissionScope::ProductGroup => $rule->scope_value !== null
                && $document->netOfCategory($rule->scope_value) !== null,
        };
    }
}
