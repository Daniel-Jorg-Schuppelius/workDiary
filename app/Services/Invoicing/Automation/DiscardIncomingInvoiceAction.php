<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiscardIncomingInvoiceAction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Automation;

use App\Automation\Actions\RuleAction;
use App\Models\Invoicing\IncomingEInvoice;
use App\Services\Invoicing\EInvoice\IncomingEInvoiceService;
use Illuminate\Database\Eloquent\Model;

/**
 * Aktion „Verwerfen“ (Phase 150, MVP-1210): der Eingang wird samt Dokument,
 * Dateien und Anhängen entfernt; es bleibt die Audit-Zeile. Nur für Belege,
 * die ein Mensch per Regel zu Nicht-Rechnungen erklärt hat.
 */
final class DiscardIncomingInvoiceAction implements RuleAction {
    public const TYPE = 'incoming_invoice.discard';

    public function __construct(private readonly IncomingEInvoiceService $invoices) {}

    public function type(): string {
        return self::TYPE;
    }

    public function label(): string {
        return (string) __('automation.action.incoming_invoice_discard');
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
        $note = trim((string) ($params['note'] ?? ''));
        $this->invoices->discard($subject, $note !== '' ? $note : (string) __('Laut Posteingangsregel verworfen.'), null, 'rule');

        return ['discarded' => true];
    }
}
