<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DunningProposalTool.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Mcp;

use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, User};
use App\Services\Invoicing\DunningService;
use App\Services\Mcp\GuardedTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\{Request, Response, ResponseFactory};
use Laravel\Mcp\Server\Attributes\{Description, Name};
use Laravel\Mcp\Server\Tools\Annotations\{IsIdempotent, IsReadOnly};

/**
 * MCP (MVP-1064): Mahnvorschlag — Rechnungen, die reif für die nächste
 * Mahnstufe sind, mit Gebühr, Zinsen und Gesamtforderung. Gemahnt wird in
 * workDiary (Mahnlauf oder Einzelmahnung), nicht über MCP.
 */
#[Name('dunning_proposal')]
#[Description('Mahnvorschlag: Rechnungen, die reif für die nächste Mahnstufe sind, mit Stufe, offenem Betrag, Gebühr, Verzugszinsen und Gesamtforderung. Gemahnt wird in workDiary.')]
#[IsReadOnly]
#[IsIdempotent]
final class DunningProposalTool extends GuardedTool {
    public function __construct(private readonly DunningService $dunning) {}

    public function schema(JsonSchema $schema): array {
        return [
            'limit' => $schema->integer()->min(1)->max(50)->description('Höchstzahl der Rechnungen, Standard 50.'),
        ];
    }

    protected function routeName(): string {
        return 'invoices.show';
    }

    protected function authorize(User $user): bool {
        return $user->canManageBilling();
    }

    protected function respond(Request $request, User $user, Organization $organization): ResponseFactory {
        $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:50']]);
        $proposals = [];
        $candidates = Invoice::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid])
            ->whereNotIn('type', [Invoice::TYPE_PROFORMA, Invoice::TYPE_CANCELLATION, Invoice::TYPE_CREDIT_NOTE])
            ->whereNull('dunning_blocked_at')
            ->whereNotNull('due_on')
            ->where('due_on', '<', now()->toDateString())
            ->with('customer:id,name')
            ->orderBy('due_on')
            ->get();
        foreach ($candidates as $invoice) {
            if (! $this->dunning->isLocallyBilled($invoice) || ! $this->dunning->isReadyForNextStep($invoice)) {
                continue;
            }
            $level = (int) $invoice->dunning_level + 1;
            $fee = $this->dunning->stepConfig($level)['fee'];
            $interest = $this->dunning->interest($invoice);
            $proposals[] = [
                'id' => $invoice->sqid,
                'number' => $invoice->number,
                'customer' => ['id' => $invoice->customer->sqid, 'name' => $invoice->customer->name],
                'due_on' => $invoice->due_on?->toDateString(),
                'next_level' => $level,
                'open_amount' => $this->dunning->openAmount($invoice),
                'fee' => $fee,
                'interest' => $interest,
                'claim_total' => $this->dunning->claimTotal($invoice, $fee, $interest),
            ];
            if (count($proposals) >= $this->limit($request, 50)) {
                break;
            }
        }

        return Response::structured(['proposals' => $proposals]);
    }
}
