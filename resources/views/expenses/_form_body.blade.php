{{-- Shared form fields for Expense --}}

<x-form-group :legend="__('Spese')" icon="receipt_long" tone="primary" cols="2">
    <x-input-field name="date" type="date" :label="__('Datum')" required :value="old('date', $date)" />
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
            <x-button :href="route('per-diem-trips.create')" tone="primary">
                {{ __('Pauschale erfassen') }}
            </x-button>
        </div>
    </div>
    <x-input-field name="vendor" :label="__('Beleg / Anbieter')" maxlength="160" placeholder="{{ __('z. B. Bahn, Hotel-Name …') }}" :value="old('vendor', $expense?->vendor)" />
    <x-select-field name="payment_method" :label="__('Zahlungsart')" required>
        @foreach ($paymentMethods as $pm)
            <option value="{{ $pm->value }}"
                    @selected(old('payment_method', $expense?->payment_method?->value ?? \App\Enums\Expense\PaymentMethod::PrivatePaid->value) === $pm->value)>
                {{ $pm->label() }}
            </option>
        @endforeach
    </x-select-field>
    <x-input-field name="description" :label="__('Beschreibung')" required maxlength="500" span="2" :value="old('description', $expense?->description)" />
</x-form-group>

<x-form-group :legend="__('Betrag')" icon="payments" tone="info" cols="3">
    <x-input-field name="amount_gross" type="number" :label="__('Brutto')" required step="0.01" min="0" data-expense-gross :value="old('amount_gross', $expense?->amount_gross)" />
    <x-input-field name="tax_rate" type="number" :label="__('Steuersatz (%)')" step="0.01" min="0" max="100" data-expense-tax-rate placeholder="{{ __('Aus Kategorie') }}" :value="old('tax_rate', $expense?->tax_rate)" />
    <x-input-field name="amount_net" type="number" :label="__('Netto (optional)')" step="0.01" min="0" placeholder="{{ __('Wird ausgerechnet') }}" :value="old('amount_net', $expense?->amount_net)" />
    <x-input-field name="currency" :label="__('Währung')" maxlength="3" minlength="3" class="input-sm w-24 uppercase" :value="old('currency', $expense?->currency ?? 'EUR')" />
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
    <x-select-field name="project_id" :label="__('Projekt')" data-depends-on="customer_id">
        <option value="">—</option>
        @foreach ($projects as $p)
            <option value="{{ $p->sqid }}" data-parent="{{ \App\Support\Sqid::encode(\App\Models\Customer::class, $p->customer_id) }}" @selected((string) old('project_id', \App\Support\Sqid::encode(\App\Models\Project::class, $expense?->project_id)) === $p->sqid)>{{ $p->name }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="customer_id" :label="__('Kunde')">
        <option value="">—</option>
        @foreach ($customers as $c)
            <option value="{{ $c->sqid }}" @selected((string) old('customer_id', \App\Support\Sqid::encode(\App\Models\Customer::class, $expense?->customer_id)) === $c->sqid)>{{ $c->name }}</option>
        @endforeach
    </x-select-field>
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
<script @cspNonce>
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
