{{-- Variablen: $expense (Model|null), $date, $categories, $projects, $customers, $paymentMethods --}}
@php
    $action = $expense
        ? route('expenses.update', $expense)
        : route('expenses.store');
@endphp

<x-modal
    :title="$expense ? __('Spese bearbeiten') : __('Neue Spese erfassen')"
    :eyebrow="__('Spesen & Auslagen')"
    icon="receipt_long"
    tone="primary"
    :action="$action"
    :method="$expense ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '', 'enctype' => 'multipart/form-data']"
    :submit-label="$expense ? __('Speichern') : __('Erfassen')">

    @include('expenses._form_body', ['expense' => $expense ?? null])

    @if ($expense)
        <x-slot:footerExtra>
            <form method="POST" action="{{ route('expenses.destroy', $expense) }}" class="inline"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Spese wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </form>
        </x-slot:footerExtra>
    @endif
</x-modal>
