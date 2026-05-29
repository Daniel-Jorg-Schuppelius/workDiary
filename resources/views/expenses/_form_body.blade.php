{{-- Shared form fields for Expense --}}

<x-form-group :legend="__('Spese')" icon="receipt_long" tone="primary" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Datum') }} *</label>
        <input type="date" name="date" required
               value="{{ old('date', $date) }}"
               class="input input-bordered w-full">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Kategorie') }}</label>
        <select name="expense_category_id" class="select select-bordered w-full" data-expense-category>
            <option value="">—</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->sqid }}"
                        data-tax-rate="{{ $cat->default_tax_rate }}"
                        data-billable-default="{{ $cat->default_billable ? '1' : '0' }}"
                        data-slug="{{ $cat->slug }}"
                        @selected((string) old('expense_category_id', \App\Support\Sqid::encode(\App\Models\ExpenseCategory::class, $expense?->expense_category_id)) === $cat->sqid)>
                    {{ $cat->label }}
                </option>
            @endforeach
        </select>
        <div data-meals-hint class="alert alert-info mt-2 hidden">
            <x-icon name="restaurant_menu" />
            <div class="flex-1 text-sm">
                {{ __('Für Verpflegung gilt im Regelfall die gesetzliche Pauschale (Verpflegungsmehraufwand). Tatsächliche Kosten sind hier nur abzurechnen, wenn ausdrücklich erlaubt.') }}
            </div>
            <a href="{{ route('per-diem-trips.create') }}" class="btn btn-sm btn-primary">
                {{ __('Pauschale erfassen') }}
            </a>
        </div>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Beleg / Anbieter') }}</label>
        <input type="text" name="vendor" maxlength="160"
               value="{{ old('vendor', $expense?->vendor) }}"
               class="input input-bordered w-full"
               placeholder="{{ __('z. B. Bahn, Hotel-Name …') }}">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Zahlungsart') }} *</label>
        <select name="payment_method" required class="select select-bordered w-full">
            @foreach ($paymentMethods as $pm)
                <option value="{{ $pm->value }}"
                        @selected(old('payment_method', $expense?->payment_method?->value ?? \App\Enums\Expense\PaymentMethod::PrivatePaid->value) === $pm->value)>
                    {{ $pm->label() }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Beschreibung') }} *</label>
        <input type="text" name="description" required maxlength="500"
               value="{{ old('description', $expense?->description) }}"
               class="input input-bordered w-full">
    </div>
</x-form-group>

<x-form-group :legend="__('Betrag')" icon="payments" tone="info" cols="3">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Brutto') }} *</label>
        <input type="number" step="0.01" min="0" name="amount_gross" required
               value="{{ old('amount_gross', $expense?->amount_gross) }}"
               class="input input-bordered w-full"
               data-expense-gross>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Steuersatz (%)') }}</label>
        <input type="number" step="0.01" min="0" max="100" name="tax_rate"
               value="{{ old('tax_rate', $expense?->tax_rate) }}"
               class="input input-bordered w-full"
               data-expense-tax-rate
               placeholder="{{ __('Aus Kategorie') }}">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Netto (optional)') }}</label>
        <input type="number" step="0.01" min="0" name="amount_net"
               value="{{ old('amount_net', $expense?->amount_net) }}"
               class="input input-bordered w-full"
               placeholder="{{ __('Wird ausgerechnet') }}">
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Währung') }}</label>
        <input type="text" name="currency" maxlength="3" minlength="3"
               value="{{ old('currency', $expense?->currency ?? 'EUR') }}"
               class="input input-bordered input-sm w-24 uppercase">
    </div>
    <div class="fieldset md:col-span-2">
        <label class="label cursor-pointer justify-start gap-3">
            <input type="hidden" name="billable" value="0">
            <input type="checkbox" name="billable" value="1"
                   @checked(old('billable', $expense?->billable ?? false)) class="checkbox checkbox-sm"
                   data-expense-billable>
            <span class="fieldset-label">{{ __('An Kunden weiterberechnen') }}</span>
        </label>
    </div>
</x-form-group>

<x-form-group :legend="__('Zuordnung')" icon="link" tone="success" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Projekt') }}</label>
        <select name="project_id" class="select select-bordered w-full" data-depends-on="customer_id">
            <option value="">—</option>
            @foreach ($projects as $p)
                <option value="{{ $p->id }}" data-parent="{{ $p->customer_id }}" @selected(old('project_id', $expense?->project_id) == $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Kunde') }}</label>
        <select name="customer_id" class="select select-bordered w-full">
            <option value="">—</option>
            @foreach ($customers as $c)
                <option value="{{ $c->sqid }}" @selected((string) old('customer_id', \App\Support\Sqid::encode(\App\Models\Customer::class, $expense?->customer_id)) === $c->sqid)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
</x-form-group>

@if (! $expense || in_array($expense->status, [\App\Enums\Expense\ExpenseStatus::Draft, \App\Enums\Expense\ExpenseStatus::Rejected], true))
    <div class="rounded-box border border-warning/40 bg-warning/10 p-3">
        <label class="label cursor-pointer justify-start gap-3">
            <input type="checkbox" name="submit_after_save" value="1" class="checkbox checkbox-sm checkbox-warning">
            <span class="text-sm font-medium">{{ __('Beim Speichern direkt zur Genehmigung einreichen') }}</span>
        </label>
        @if ($expense?->status === \App\Enums\Expense\ExpenseStatus::Rejected && $expense->reject_reason)
            <p class="mt-2 text-xs text-error">
                <strong>{{ __('Ablehnungsgrund:') }}</strong> {{ $expense->reject_reason }}
            </p>
        @endif
    </div>
@endif

@push('scripts')
<script>
    (() => {
        const root = document.currentScript.previousElementSibling?.closest('dialog') || document;
        const catSelect = root.querySelector('[data-expense-category]');
        const taxInput  = root.querySelector('[data-expense-tax-rate]');
        const billable  = root.querySelector('[data-expense-billable]');
        const mealsHint = root.querySelector('[data-meals-hint]');
        if (! catSelect) return;
        const refreshMealsHint = () => {
            if (! mealsHint) return;
            const opt = catSelect.options[catSelect.selectedIndex];
            const isMeals = !! opt && opt.dataset.slug === 'meals';
            mealsHint.classList.toggle('hidden', ! isMeals);
        };
        refreshMealsHint();
        catSelect.addEventListener('change', () => {
            const opt = catSelect.options[catSelect.selectedIndex];
            if (! opt) return;
            // Steuersatz nur befüllen, wenn Feld leer ist
            if (taxInput && taxInput.value.trim() === '' && opt.dataset.taxRate) {
                taxInput.value = opt.dataset.taxRate;
            }
            // Billable-Default nur beim Neuanlegen anwenden
            if (billable && opt.dataset.billableDefault === '1' && ! billable.dataset.userTouched) {
                billable.checked = true;
            }
            refreshMealsHint();
        });
        billable?.addEventListener('change', () => { billable.dataset.userTouched = '1'; });
    })();
</script>
@endpush
