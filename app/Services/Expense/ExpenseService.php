<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Expense;

use App\Enums\Expense\ExpenseStatus;
use App\Models\{Expense, ExpenseCategory, User};
use App\Notifications\Expense\{ExpenseDecidedNotification, ExpenseSubmittedNotification};
use Illuminate\Support\Facades\{DB, Notification};

/**
 * Kapselt Persistenz und Statuswechsel von {@see Expense}.
 *
 * Datenfluss:
 *  - applyDefaults() zieht Steuersatz / Billable-Default aus der Kategorie,
 *    Währung aus invoicing-Config.
 *  - Brutto/Netto-Synchronisierung passiert im Model `booted()`.
 *  - Statuswechsel triggern E-Mail/Database-Notifications an Approver
 *    bzw. den Eigentümer (Kanal-Auswahl liegt im Notification-Class).
 */
class ExpenseService {
    public function __construct(
        private readonly ApproverResolver $approverResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Expense {
        return DB::transaction(function () use ($attributes): Expense {
            $attributes = $this->applyDefaults($attributes);
            $attributes['status'] = ExpenseStatus::Draft->value;
            $expense = Expense::create($attributes);

            return $expense->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Expense $expense, array $attributes): Expense {
        return DB::transaction(function () use ($expense, $attributes): Expense {
            $attributes = $this->applyDefaults($attributes, $expense);
            // Statuswechsel bleibt eigenen Service-Methoden vorbehalten.
            unset($attributes['status']);
            $expense->fill($attributes);
            $expense->save();

            return $expense->refresh();
        });
    }

    public function delete(Expense $expense): void {
        DB::transaction(function () use ($expense): void {
            $expense->delete();
        });
    }

    public function submitForApproval(Expense $expense): Expense {
        $wasSubmitted = false;

        $expense = DB::transaction(function () use ($expense, &$wasSubmitted): Expense {
            if ($expense->status === ExpenseStatus::Draft || $expense->status === ExpenseStatus::Rejected) {
                $expense->status = ExpenseStatus::Pending;
                $expense->reject_reason = null;
                $expense->decided_at = null;
                $expense->decided_by = null;
                $expense->save();
                $wasSubmitted = true;
            }

            return $expense->refresh();
        });

        if ($wasSubmitted) {
            $expense->loadMissing('user');
            // Automation-Hook: eine aktive Regel kann direkt approve/route. Vor der Approver-Notification,
            // damit auto-approve eine Entscheidungs- statt Anfrage-Benachrichtigung auslöst.
            try {
                app(\App\Automation\RuleEngine::class)->dispatch('expense.submitted', $expense);
                $expense->refresh();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('automation: expense.submitted dispatch failed', [
                    'expense_id' => $expense->id, 'error' => $e->getMessage(),
                ]);
            }

            if ($expense->status === ExpenseStatus::Pending) {
                $approvers = $this->approverResolver->approversFor($expense);
                if ($approvers->isNotEmpty()) {
                    Notification::send($approvers, new ExpenseSubmittedNotification($expense));
                }
            }
        }

        return $expense;
    }

    public function cancel(Expense $expense): Expense {
        return DB::transaction(function () use ($expense): Expense {
            $expense->status = ExpenseStatus::Cancelled;
            $expense->save();

            return $expense->refresh();
        });
    }

    public function approve(Expense $expense, User $approver): Expense {
        $expense = DB::transaction(function () use ($expense, $approver): Expense {
            $expense->status = ExpenseStatus::Approved;
            $expense->decided_by = $approver->id;
            $expense->decided_at = now();
            $expense->reject_reason = null;
            $expense->save();

            return $expense->refresh();
        });

        $this->notifyOwner($expense);

        return $expense;
    }

    public function reject(Expense $expense, User $approver, ?string $reason = null): Expense {
        $expense = DB::transaction(function () use ($expense, $approver, $reason): Expense {
            $expense->status = ExpenseStatus::Rejected;
            $expense->decided_by = $approver->id;
            $expense->decided_at = now();
            $expense->reject_reason = $reason;
            $expense->save();

            return $expense->refresh();
        });

        $this->notifyOwner($expense);

        return $expense;
    }

    public function markReimbursed(Expense $expense, ?string $reference = null): Expense {
        $expense = DB::transaction(function () use ($expense, $reference): Expense {
            $expense->status = ExpenseStatus::Reimbursed;
            $expense->reimbursed_at = now();
            if ($reference !== null && $reference !== '') {
                $expense->reimbursement_reference = $reference;
            }
            $expense->save();

            return $expense->refresh();
        });

        $this->notifyOwner($expense);

        return $expense;
    }

    private function notifyOwner(Expense $expense): void {
        $owner = $expense->user;
        if ($owner instanceof User) {
            $owner->notify(new ExpenseDecidedNotification($expense));
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function applyDefaults(array $attributes, ?Expense $existing = null): array {
        $categoryId = $attributes['expense_category_id'] ?? $existing?->expense_category_id;
        $category = $categoryId !== null ? ExpenseCategory::query()->find((int) $categoryId) : null;

        // W2.3: dokumentierte Fallback-Kette expenses.* → invoicing.* verdrahtet.
        if (! array_key_exists('currency', $attributes) || ! is_string($attributes['currency']) || $attributes['currency'] === '') {
            $attributes['currency'] = $existing !== null
                ? $existing->currency
                : (string) (config('expenses.default_currency') ?? config('invoicing.default_currency', 'EUR'));
        }

        if (! array_key_exists('tax_rate', $attributes) || $attributes['tax_rate'] === null || $attributes['tax_rate'] === '') {
            if ($category !== null) {
                $attributes['tax_rate'] = (string) $category->default_tax_rate;
            } else {
                $attributes['tax_rate'] = $existing !== null
                    ? $existing->tax_rate
                    : (string) (config('expenses.default_tax_rate') ?? config('invoicing.default_tax_rate', '19.00'));
            }
        }

        if (! array_key_exists('billable', $attributes) && $existing === null && $category !== null) {
            $attributes['billable'] = (bool) $category->default_billable;
        }

        return $attributes;
    }
}
