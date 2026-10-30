<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceRules.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\EInvoice;

use App\Enums\Billing\DocumentDirection;
use App\Enums\Invoicing\IncomingEInvoiceStatus;
use App\Models\Automation\{AutomationRule, AutomationRuleRun};
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\User;
use App\Services\Invoicing\Automation\{AssignIncomingInvoiceAction, DiscardIncomingInvoiceAction, IncomingInvoiceReceivedTrigger, RejectIncomingInvoiceAction};
use Illuminate\Database\Eloquent\Collection;

/**
 * Posteingangsregeln (Phase 150, MVP-1211): Automationsregeln zum Auslöser
 * „Rechnungseingang eingegangen“ anlegen, lesen und beschreiben — die
 * Sicht der Buchhaltung auf die Regelmaschine. Entscheidungen (ablehnen)
 * laufen hier, damit Dialog, Regelaktion und Sammelaktion dasselbe tun.
 */
class IncomingInvoiceRules {
    /** Priorität der Ausschlüsse aus dem Dialog: vor Nutzerregeln (Standard 100). */
    public const EXCLUSION_PRIORITY = 50;

    /** @var array<string, string> Bedingungsfeld → Textschlüssel */
    public const FIELD_LABELS = [
        'seller_name_key' => 'Verkäufer',
        'buyer_name_key' => 'Käufer',
        'party_name_key' => 'Gegenpartei',
        'seller_name' => 'Verkäufer',
        'buyer_name' => 'Käufer',
        'seller_vat_id' => 'USt-IdNr.',
        'sender_email' => 'Absender',
        'sender_domain' => 'Absender-Domain',
        'mail_subject' => 'Betreff',
        'attachment_names' => 'Anhänge',
        'file_name' => 'Dateiname',
        'invoice_number' => 'Rechnungsnummer',
        'amount_gross' => 'Brutto',
        'country' => 'Land',
        'recognition' => 'Erkennung',
        'direction' => 'Richtung',
        'source' => 'Kanal',
    ];

    /** @var array<string, string> Operator → Textschlüssel */
    public const OP_LABELS = [
        '=' => 'ist', '==' => 'ist', '!=' => 'ist nicht', '<>' => 'ist nicht',
        '<' => 'kleiner als', '<=' => 'höchstens', '>' => 'größer als', '>=' => 'mindestens',
        'in' => 'ist eines von', 'not_in' => 'ist keines von',
        'contains' => 'enthält', 'not_contains' => 'enthält nicht',
        'starts_with' => 'beginnt mit', 'ends_with' => 'endet mit', 'matches' => 'entspricht',
    ];

    /**
     * Status „abgelehnt“ mit Begründung; ohne Akteur ist es eine Regel.
     *
     * @param  array<string, mixed>  $audit
     */
    public function reject(IncomingEInvoice $incoming, string $note, ?User $actor, array $audit = []): void {
        $incoming->forceFill([
            'status' => IncomingEInvoiceStatus::Rejected,
            'decided_by' => $actor?->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ])->save();
        $incoming->audit('incoming_einvoice.decided', ['to' => IncomingEInvoiceStatus::Rejected->value, 'reason' => 'not_invoice', ...$audit]);
    }

    /**
     * Gegenpartei des Eingangs künftig ausschließen: eine Regel auf den
     * normalisierten Namen, Aktion „ablehnen“ oder „verwerfen“ (nur auf
     * ausdrücklichen Wunsch im Dialog); `$action` ist `reject` oder `discard`.
     */
    public function rememberExclusion(IncomingEInvoice $incoming, User $actor, string $action, ?string $note): ?AutomationRule {
        $name = $incoming->counterpartyName();
        $key = IncomingEInvoice::nameKey($name);
        if ($name === null || $key === null) {
            return null;
        }
        $field = $incoming->direction === DocumentDirection::Outgoing ? 'buyer_name_key' : 'seller_name_key';
        $note = $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null;
        $rule = AutomationRule::query()->withoutGlobalScopes()->updateOrCreate([
            'organization_id' => $incoming->organization_id,
            'trigger_event' => IncomingInvoiceReceivedTrigger::KEY,
            'name' => mb_substr((string) __('Ausschluss: :party', ['party' => trim($name)]), 0, 255),
        ], [
            'conditions' => ['all' => [['field' => $field, 'op' => '=', 'value' => $key]]],
            'actions' => [['type' => $action === 'discard' ? DiscardIncomingInvoiceAction::TYPE : RejectIncomingInvoiceAction::TYPE, 'params' => array_filter(['note' => $note])]],
            'is_active' => true,
            'priority' => self::EXCLUSION_PRIORITY,
            'created_by_id' => $actor->id,
        ]);
        $incoming->audit('incoming_einvoice.rule_saved', ['rule_id' => $rule->id, 'party' => trim($name), 'action' => $action]);

        return $rule;
    }

    /** @return Collection<int, AutomationRule> Regeln des Auslösers, nach Priorität */
    public function rulesFor(int $organizationId): Collection {
        return AutomationRule::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('trigger_event', IncomingInvoiceReceivedTrigger::KEY)
            ->orderBy('priority')->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, AutomationRule>  $rules
     * @return array<int, int> Regel-ID → Treffer
     */
    public function hits(Collection $rules): array {
        if ($rules->isEmpty()) {
            return [];
        }

        return AutomationRuleRun::query()
            ->whereIn('rule_id', $rules->modelKeys())
            ->where('decision', 'matched')
            ->selectRaw('rule_id, count(*) as hits')
            ->groupBy('rule_id')
            ->pluck('hits', 'rule_id')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }

    /** Bedingungen in Worten, z. B. „Verkäufer ist beispiel-konverter.de und Betreff enthält Test“. */
    public function describeConditions(AutomationRule $rule): string {
        $conditions = (array) ($rule->conditions ?: []);
        $groups = [];
        foreach (['all' => ' ' . __('und') . ' ', 'any' => ' ' . __('oder') . ' '] as $group => $glue) {
            $parts = [];
            foreach ((array) ($conditions[$group] ?? []) as $predicate) {
                if (! is_array($predicate)) {
                    continue;
                }
                $field = (string) ($predicate['field'] ?? '');
                $op = (string) ($predicate['op'] ?? '=');
                $value = $predicate['value'] ?? null;
                $parts[] = trim(sprintf(
                    '%s %s %s',
                    __(self::FIELD_LABELS[$field] ?? $field),
                    __(self::OP_LABELS[$op] ?? $op),
                    is_array($value) ? implode(', ', array_map(strval(...), $value)) : (string) $value,
                ));
            }
            if ($parts !== []) {
                $groups[] = implode((string) $glue, $parts);
            }
        }

        return $groups === [] ? (string) __('immer') : implode('; ', $groups);
    }

    /** Aktion der Regel in Worten, mit Begründung bzw. Partei. */
    public function describeAction(AutomationRule $rule): string {
        $spec = (array) (((array) ($rule->actions ?: []))[0] ?? []);
        $type = (string) ($spec['type'] ?? '');
        $params = (array) ($spec['params'] ?? []);
        $label = match ($type) {
            DiscardIncomingInvoiceAction::TYPE => (string) __('Verwerfen'),
            RejectIncomingInvoiceAction::TYPE => (string) __('Keine Rechnung'),
            AssignIncomingInvoiceAction::TYPE => (string) __('Zuordnen'),
            default => $type,
        };
        $detail = $type === AssignIncomingInvoiceAction::TYPE
            ? app(AssignIncomingInvoiceAction::class)->partyLabel((int) $rule->organization_id, (string) ($params['party'] ?? ''))
            : (string) ($params['note'] ?? '');

        return $detail !== '' ? $label . ' · ' . $detail : $label;
    }
}
