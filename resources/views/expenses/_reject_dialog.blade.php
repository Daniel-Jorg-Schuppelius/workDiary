{{-- Variablen: $expense --}}
<x-modal
    :title="__('Spese ablehnen')"
    :eyebrow="__('Spesen-Genehmigung')"
    icon="block"
    tone="error"
    :action="route('expense-approvals.reject', $expense)"
    method="POST"
    :submit-label="__('Ablehnen')"
    submit-tone="error">

    <p class="text-sm text-base-content/70">
        {{ __('Bitte gib einen kurzen Grund an. Der Eigentümer kann die Spese danach überarbeiten und erneut einreichen.') }}
    </p>

    <div class="alert alert-info mt-3 text-sm">
        <div>
            <div class="font-semibold">{{ $expense->user?->name }}</div>
            <div class="text-base-content/70">
                {{ $expense->date->fdate() }} ·
                {{ number_format((float) $expense->amount_gross, 2, ',', '.') }} {{ $expense->currency->value }}
            </div>
            @if ($expense->description)
                <div class="mt-1">{{ $expense->description }}</div>
            @endif
        </div>
    </div>

    <div class="fieldset mt-3">
        <label class="fieldset-label" for="reject_reason">{{ __('Ablehnungsgrund') }}</label>
        <textarea id="reject_reason" name="reject_reason"
                  class="textarea textarea-bordered w-full"
                  rows="4"
                  maxlength="500"
                  placeholder="{{ __('z. B. Beleg fehlt, falsche Kategorie, doppelt erfasst …') }}"></textarea>
    </div>
</x-modal>
