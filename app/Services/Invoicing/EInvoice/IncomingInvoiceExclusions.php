<?php
/*
 * Created on   : Sun Oct 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceExclusions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\EInvoice;

use App\Enums\Billing\DocumentDirection;
use App\Enums\Invoicing\IncomingEInvoiceStatus;
use App\Models\Invoicing\{IncomingEInvoice, IncomingInvoiceExclusion};
use App\Models\Platform\User;

/**
 * Ausschlussregeln des Rechnungseingangs (Feature 163, MVP-1208): Eingänge
 * einer ausgeschlossenen Gegenpartei werden sofort als „keine Rechnung“
 * abgelegt — beim Abruf, Upload und bei der Nacherkennung. Der Datensatz
 * bleibt samt Original erhalten, nur der Status ist entschieden.
 */
class IncomingInvoiceExclusions {
    public function matching(IncomingEInvoice $incoming): ?IncomingInvoiceExclusion {
        $key = IncomingInvoiceExclusion::keyFor($incoming->counterpartyName());
        if ($key === null) {
            return null;
        }

        return IncomingInvoiceExclusion::query()->withoutGlobalScopes()
            ->where('organization_id', $incoming->organization_id)
            ->where('direction', self::direction($incoming)->value)
            ->where('party_name_key', $key)
            ->first();
    }

    /** Legt den Eingang abgelehnt ab, wenn eine Regel auf seine Gegenpartei passt. */
    public function applyTo(IncomingEInvoice $incoming): bool {
        if ($incoming->transferred_at !== null || ! $incoming->status->canTransitionTo(IncomingEInvoiceStatus::Rejected)) {
            return false;
        }
        $rule = $this->matching($incoming);
        if ($rule === null) {
            return false;
        }
        $note = trim((string) $rule->note);
        $this->reject($incoming, $note !== '' ? $note : (string) __('Laut Ausschlussregel keine Rechnung.'), null, ['exclusion_id' => $rule->id]);

        return true;
    }

    /**
     * Status „abgelehnt“ mit Begründung; ohne Akteur ist es die Regel.
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

    /** Gegenpartei des Eingangs künftig ausschließen (nur auf ausdrücklichen Wunsch). */
    public function remember(IncomingEInvoice $incoming, User $actor, ?string $note): ?IncomingInvoiceExclusion {
        $name = $incoming->counterpartyName();
        $key = IncomingInvoiceExclusion::keyFor($name);
        if ($name === null || $key === null) {
            return null;
        }
        $rule = IncomingInvoiceExclusion::query()->withoutGlobalScopes()->updateOrCreate([
            'organization_id' => $incoming->organization_id,
            'direction' => self::direction($incoming)->value,
            'party_name_key' => $key,
        ], [
            'party_name' => mb_substr(trim($name), 0, 191),
            'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
            'created_by' => $actor->id,
        ]);
        $rule->audit('incoming_einvoice.exclusion_saved', ['party' => $rule->party_name]);

        return $rule;
    }

    private static function direction(IncomingEInvoice $incoming): DocumentDirection {
        return $incoming->direction === DocumentDirection::Outgoing ? DocumentDirection::Outgoing : DocumentDirection::Incoming;
    }
}
