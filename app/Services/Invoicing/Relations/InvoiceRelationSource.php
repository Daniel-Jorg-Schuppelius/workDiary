<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceRelationSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Relations;

use App\Enums\Billing\DocumentRelationKind;
use App\Enums\User\Permission;
use App\Models\Invoicing\{Invoice, InvoiceItem, InvoiceScheduleRun};
use App\Services\Billing\Contracts\DocumentRelationSource;
use App\Services\Billing\Dto\{DocumentNode, DocumentRef, DocumentRelation};
use Illuminate\Support\Facades\Gate;

/**
 * Bezüge zwischen Rechnungsbelegen (MVP-1157): Gutschrift und Storno zum
 * Original, Pro-forma zur Rechnung, Abschlag zur Schlussrechnung, Rechnung
 * zum Abrechnungsplan.
 */
final class InvoiceRelationSource implements DocumentRelationSource {
    public function relationsOf(DocumentRef $document, int $organizationId): array {
        if (! $document->is(Invoice::class)) {
            return [];
        }
        $invoice = Invoice::query()->where('organization_id', $organizationId)->find($document->id);
        if (! $invoice instanceof Invoice) {
            return [];
        }

        $relations = [];
        if ($invoice->parent_invoice_id !== null) {
            $parent = Invoice::query()->where('organization_id', $organizationId)->find($invoice->parent_invoice_id);
            if ($parent instanceof Invoice) {
                $relations[] = new DocumentRelation(InvoiceNode::of($parent), self::isCorrection($invoice) ? DocumentRelationKind::Corrects : DocumentRelationKind::Source);
            }
        }
        foreach (Invoice::query()->where('organization_id', $organizationId)->where('parent_invoice_id', $invoice->id)->get() as $child) {
            $relations[] = new DocumentRelation(InvoiceNode::of($child), self::isCorrection($child) ? DocumentRelationKind::CorrectedBy : DocumentRelationKind::FollowUp);
        }

        $settledIds = InvoiceItem::query()->where('organization_id', $organizationId)->where('invoice_id', $invoice->id)->whereNotNull('settled_invoice_id')->pluck('settled_invoice_id');
        foreach (Invoice::query()->where('organization_id', $organizationId)->whereIn('id', $settledIds)->get() as $downPayment) {
            $relations[] = new DocumentRelation(InvoiceNode::of($downPayment), DocumentRelationKind::Settles);
        }
        $settlingIds = InvoiceItem::query()->where('organization_id', $organizationId)->where('settled_invoice_id', $invoice->id)->pluck('invoice_id');
        foreach (Invoice::query()->where('organization_id', $organizationId)->whereIn('id', $settlingIds)->get() as $final) {
            $relations[] = new DocumentRelation(InvoiceNode::of($final), DocumentRelationKind::SettledBy);
        }

        // Der Plan ist ein Blatt: Über ihn würden alle Rechnungen des Plans zu Nachbarn.
        $schedule = InvoiceScheduleRun::query()->where('organization_id', $organizationId)->where('invoice_id', $invoice->id)->first()?->schedule;
        if ($schedule !== null) {
            $relations[] = new DocumentRelation(new DocumentNode(
                ref: DocumentRef::forModel($schedule),
                label: (string) __('billing.relations.schedule'),
                number: (string) $schedule->title,
                state: $schedule->status->label(),
                url: Gate::allows(Permission::InvoiceViewAny->value) ? route('invoice-schedules.show', $schedule) : null,
            ), DocumentRelationKind::Source);
        }

        return $relations;
    }

    private static function isCorrection(Invoice $invoice): bool {
        return $invoice->isCreditNote() || $invoice->type === Invoice::TYPE_CANCELLATION;
    }
}
