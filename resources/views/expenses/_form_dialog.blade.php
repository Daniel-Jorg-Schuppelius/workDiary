{{--
  Created on   : Fri May 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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

    {{-- MVP-802: Korrektur einer übergebenen Auslage — Bezug und Erstattungshinweis. --}}
    @if ($expense?->corrects)
        <div role="note" class="alert alert-warning mb-3 text-sm">
            <div>
                <p>{{ __('Korrektur zu Auslage #:id (Gegenbeleg übergeben). Grund: :reason', ['id' => $expense->corrects->id, 'reason' => $expense->correction_reason]) }}</p>
                @if ($expense->corrects->status === \App\Enums\Expense\ExpenseStatus::Reimbursed)
                    <p class="mt-1 font-semibold">{{ __('Die ursprüngliche Auslage wurde bereits erstattet — bei der Erstattung dieser Korrektur nur die Differenz auszahlen.') }}</p>
                @endif
            </div>
        </div>
    @endif

    @include('expenses._form_body', ['expense' => $expense ?? null])

    @if ($expense)
        <x-slot:footerExtra>
            <x-action-form :action="route('expenses.destroy', $expense)" method="DELETE"
                  :confirm="__('Spese wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
