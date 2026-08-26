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
use App\Models\{Invoice, InvoiceItem, Lead, User};
use App\Models\Sales\CommissionRule;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Carbon;

/**
 * Zwei Fragen, eine Klasse (Feature 146, MVP-729):
 *
 *  1. **Wem** gehoert der Beleg? Standard ist die Herkunft aus der
 *     Lead-Pipeline (Feature 091: der konvertierte Lead traegt den
 *     Verantwortlichen); `invoices.sales_user_id` schlaegt sie als manuelle
 *     Zuordnung.
 *  2. **Welche** Regel gilt? Genau eine: hoechste Prioritaet, bei Gleichstand
 *     der engere Geltungsbereich, zuletzt die juengere Regel. Es wird nichts
 *     summiert — zwei Regeln ergeben nie zwei Provisionen.
 *
 * Die Klasse schreibt nichts. Sie wird sowohl vom Auslöser
 * ({@see CommissionAccrualService}) als auch von der Vorschau in der UI
 * benutzt, damit beide dieselbe Antwort geben.
 */
class CommissionRuleResolver {
    /**
     * Zuordnung Beleg → Vertriebsperson. `null` = niemand zustaendig, also
     * keine Provision (das ist der Normalfall ohne Vertriebsorganisation).
     */
    public function assignmentFor(Invoice $invoice): ?CommissionAssignment {
        $manual = $invoice->sales_user_id !== null
            ? User::query()->whereKey($invoice->sales_user_id)->first()
            : null;

        $lead = $this->originLead($invoice);

        if ($manual instanceof User) {
            return new CommissionAssignment($manual, CommissionAssignmentSource::Manual, $lead);
        }

        if ($lead !== null && $lead->responsible_user_id !== null) {
            $responsible = User::query()->whereKey($lead->responsible_user_id)->first();
            if ($responsible instanceof User) {
                return new CommissionAssignment($responsible, CommissionAssignmentSource::Lead, $lead);
            }
        }

        return null;
    }

    /**
     * Der Lead, aus dem der Kunde des Belegs entstanden ist — juengste
     * Konvertierung gewinnt, falls ein Kunde mehrfach angebahnt wurde.
     */
    public function originLead(Invoice $invoice): ?Lead {
        return Lead::query()
            ->where('organization_id', $invoice->organization_id)
            ->where('customer_id', $invoice->customer_id)
            ->whereNotNull('responsible_user_id')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Gueltige Regel fuer diesen Beleg am Stichtag; `null` = keine passende
     * Regel, also keine Provision.
     */
    public function ruleFor(Invoice $invoice, CommissionAssignment $assignment, Carbon $on): ?CommissionRule {
        $candidates = CommissionRule::query()
            ->where('organization_id', $invoice->organization_id)
            ->validOn($on)
            ->get()
            ->filter(fn (CommissionRule $rule): bool => $this->applies($rule, $invoice, $assignment))
            ->sortByDesc(fn (CommissionRule $rule): string => $rule->selectionKey());

        return $candidates->first();
    }

    /**
     * Bemessungsgrundlage: der Nettobetrag des Belegs — bei einer
     * Produktgruppen-Regel nur die Positionen dieser Gruppe. Nettobetrag,
     * nicht Bruttobetrag: die Umsatzsteuer ist durchlaufender Posten und
     * gehoert keinem Vertrieb.
     */
    public function baseAmountFor(Invoice $invoice, CommissionRule $rule): Money {
        $currency = $invoice->currency;
        $total = $invoice->subtotal ?? Money::zero($currency);

        if ($rule->scope !== CommissionScope::ProductGroup) {
            return $total;
        }

        $amounts = [];
        foreach ($this->itemsOfGroup($invoice, (string) $rule->scope_value) as $item) {
            $amounts[] = $item->amount ?? Money::zero($currency);
        }

        return $amounts === [] ? Money::zero($currency) : Money::sum($amounts, $currency);
    }

    /** Passt die Regel auf diesen Beleg? */
    private function applies(CommissionRule $rule, Invoice $invoice, CommissionAssignment $assignment): bool {
        return match ($rule->scope) {
            CommissionScope::All => true,
            CommissionScope::User => $rule->user_id !== null && (int) $rule->user_id === (int) $assignment->user->id,
            CommissionScope::LeadSource => $assignment->lead !== null
                && $rule->scope_value !== null
                && $assignment->lead->source->value === $rule->scope_value,
            CommissionScope::ProductGroup => $rule->scope_value !== null
                && $this->itemsOfGroup($invoice, $rule->scope_value) !== [],
        };
    }

    /**
     * Belegpositionen einer Produktgruppe. „Produktgruppe" ist die Kategorie
     * des Artikelstamms (`articles.category`) — WorkDiary fuehrt keinen
     * zweiten Gruppenbegriff nur fuer Provisionen ein.
     *
     * @return list<InvoiceItem>
     */
    private function itemsOfGroup(Invoice $invoice, string $group): array {
        $invoice->loadMissing('items.article:id,category');

        $matching = [];
        foreach ($invoice->items as $item) {
            // Waehrung der Position kommt vom Beleg (MoneyCast ':invoice.currency').
            $item->setRelation('invoice', $invoice);
            if ($item->article_id !== null && (string) $item->article?->category === $group) {
                $matching[] = $item;
            }
        }

        return $matching;
    }
}
