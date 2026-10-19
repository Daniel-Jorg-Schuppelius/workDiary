<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RejectIncomingInvoiceAction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Automation;

use App\Automation\Actions\RuleAction;
use App\Enums\Invoicing\IncomingEInvoiceStatus;
use App\Models\Invoicing\IncomingEInvoice;
use App\Services\Invoicing\EInvoice\IncomingInvoiceRules;
use Illuminate\Database\Eloquent\Model;

/** Aktion „Keine Rechnung“ (Phase 150, MVP-1210): abgelehnt ablegen, Beleg und Original bleiben. */
final class RejectIncomingInvoiceAction implements RuleAction {
    public const TYPE = 'incoming_invoice.reject';

    public function __construct(private readonly IncomingInvoiceRules $rules) {}

    public function type(): string {
        return self::TYPE;
    }

    public function label(): string {
        return (string) __('automation.action.incoming_invoice_reject');
    }

    /** @return list<string> */
    public function triggers(): array {
        return [IncomingInvoiceReceivedTrigger::KEY];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(Model $subject, array $params): array {
        if (! $subject instanceof IncomingEInvoice) {
            return ['skipped' => 'wrong_subject'];
        }
        if ($subject->transferred_at !== null || ! $subject->status->canTransitionTo(IncomingEInvoiceStatus::Rejected)) {
            return ['skipped' => 'not_rejectable'];
        }
        $note = trim((string) ($params['note'] ?? ''));
        $this->rules->reject($subject, $note !== '' ? $note : (string) __('Laut Posteingangsregel keine Rechnung.'), null, ['trigger' => 'rule']);

        return ['rejected' => true];
    }
}
