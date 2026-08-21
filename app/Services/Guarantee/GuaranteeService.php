<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuaranteeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Guarantee;

use App\Enums\Guarantee\GuaranteeStatus;
use App\Enums\Invoicing\RetentionStatus;
use App\Models\Guarantee\Guarantee;
use App\Models\Invoicing\InvoiceRetention;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bürgschaftsverwaltung (Feature 114, MVP-603).
 *
 * Der fachliche Kern ist die **Ablösung eines Sicherheitseinbehalts**
 * (MVP-602): Der Auftragnehmer stellt eine Bürgschaft, der Auftraggeber zahlt
 * dafür den einbehaltenen Betrag aus. Beides muss in einem Schritt passieren —
 * sonst steht entweder die Sicherheit doppelt (Einbehalt UND Bürgschaft) oder
 * gar nicht.
 */
class GuaranteeService {
    /**
     * Bürgschaft löst einen Sicherheitseinbehalt ab: Der Einbehalt wird
     * ausgezahlt (Status `secured`), die Bürgschaft tritt an seine Stelle.
     */
    public function secureRetention(Guarantee $guarantee, InvoiceRetention $retention, ?User $actor = null): Guarantee {
        if ($retention->status !== RetentionStatus::Open) {
            throw new RuntimeException((string) __('guarantee.retention_not_open'));
        }
        if ((int) $guarantee->organization_id !== (int) $retention->organization_id) {
            throw new RuntimeException((string) __('guarantee.foreign_organization'));
        }
        if (! $guarantee->status->isActive()) {
            throw new RuntimeException((string) __('guarantee.not_active'));
        }

        // Die Bürgschaft muss den Einbehalt mindestens decken — eine kleinere
        // Bürgschaft löst ihn nicht ab, sie ersetzt nur einen Teil der
        // Sicherheit und wäre ein stiller Verlust.
        if ($guarantee->amount->toFloat() + 0.005 < $retention->amount->toFloat()) {
            throw new RuntimeException((string) __('guarantee.amount_too_low'));
        }

        return DB::transaction(function () use ($guarantee, $retention, $actor): Guarantee {
            $retention->forceFill([
                'status' => RetentionStatus::Secured->value,
                'released_on' => CarbonImmutable::today()->toDateString(),
            ])->save();

            $guarantee->forceFill(['invoice_retention_id' => $retention->id])->save();

            $retention->invoice?->audit('invoice.retention_secured', [
                'retention_id' => $retention->id,
                'guarantee_id' => $guarantee->id,
                'amount' => (string) $retention->getAttributes()['amount'],
            ]);
            $guarantee->audit('guarantee.secured_retention', [
                'retention_id' => $retention->id,
                'by' => $actor?->id,
            ]);

            return $guarantee->refresh();
        });
    }

    /**
     * Rückgabe der Urkunde protokollieren. Ohne diesen Vermerk bleibt offen,
     * ob die Bürgschaft noch gezogen werden kann — genau die Frage, die
     * Monate später niemand mehr beantworten kann.
     */
    public function markReturned(Guarantee $guarantee, ?User $actor = null, ?string $note = null): Guarantee {
        if (! $guarantee->status->isActive()) {
            throw new RuntimeException((string) __('guarantee.not_active'));
        }

        $guarantee->forceFill([
            'status' => GuaranteeStatus::Returned->value,
            'returned_on' => CarbonImmutable::today()->toDateString(),
            'returned_note' => $note,
        ])->save();

        $guarantee->audit('guarantee.returned', ['by' => $actor?->id]);

        return $guarantee->refresh();
    }

    /** Ziehung der Bürgschaft festhalten (Rechtsentscheidung, kein Automatismus). */
    public function markDrawn(Guarantee $guarantee, ?User $actor = null, ?string $note = null): Guarantee {
        if (! $guarantee->status->isActive()) {
            throw new RuntimeException((string) __('guarantee.not_active'));
        }

        $guarantee->forceFill([
            'status' => GuaranteeStatus::Drawn->value,
            'returned_note' => $note,
        ])->save();

        $guarantee->audit('guarantee.drawn', ['by' => $actor?->id]);

        return $guarantee->refresh();
    }
}
