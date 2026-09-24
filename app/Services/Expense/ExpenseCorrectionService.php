<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseCorrectionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Expense;

use App\Enums\Expense\ExpenseStatus;
use App\Models\Platform\User;
use App\Models\Travel\Expense;
use App\Services\Billing\ExpenseLinkProviderResolver;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Korrektur einer bereits übergebenen Auslage (MVP-802, Feature 106).
 *
 * Der Beleg im Buchhaltungssystem ist unveränderlich. Korrigiert wird wie bei
 * den Faktura-Übergaben (MVP-490): Gegenbeleg im Zielsystem, die Auslage selbst
 * bleibt unverändert, und eine neue Auslage im Entwurf zeigt auf sie. Die neue
 * Auslage durchläuft Genehmigung und Übergabe wie jede andere.
 *
 * Erstattung: War die ursprüngliche Auslage genehmigt, aber noch nicht erstattet,
 * wird sie storniert — sonst würden Original und Korrektur ausgezahlt. War sie
 * schon erstattet, bleibt der Status; der Entwurf weist darauf hin.
 */
class ExpenseCorrectionService {
    public function __construct(
        private readonly ExpenseLinkProviderResolver $providers,
        private readonly ExpenseService $expenses,
    ) {}

    public function correct(Expense $original, string $reason, User $actor): Expense {
        $existing = $original->corrections()->orderBy('id')->first();
        if ($existing instanceof Expense) {
            return $existing;
        }

        $provider = $this->providers->current();
        if (! $provider->wasPushed($original)) {
            throw new RuntimeException((string) __('Nur übergebene Auslagen werden per Gegenbeleg korrigiert — alles andere lässt sich direkt bearbeiten.'));
        }

        // Zuerst der Gegenbeleg: Er ist idempotent. Scheitert danach die lokale
        // Anlage, findet ein zweiter Versuch den vorhandenen Gegenbeleg.
        $counter = $provider->pushCounterVoucher($original, $reason);

        return DB::transaction(function () use ($original, $reason, $actor, $counter): Expense {
            $copy = $original->replicate([
                'status', 'decided_by', 'decided_at', 'reject_reason', 'reimbursed_at',
                'reimbursement_reference', 'corrects_expense_id', 'correction_reason',
                'created_by', 'updated_by',
            ]);
            $copy->forceFill([
                'status' => ExpenseStatus::Draft,
                'corrects_expense_id' => $original->id,
                'correction_reason' => mb_substr($reason, 0, 500),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            if ($original->status === ExpenseStatus::Approved) {
                $this->expenses->cancel($original);
            }

            $original->audit('expense.corrected', [
                'counter_voucher' => $counter->externalId,
                'correction_expense_id' => $copy->id,
            ]);
            $copy->audit('expense.created_as_correction', ['corrects_expense_id' => $original->id]);

            return $copy;
        });
    }
}
