{{--
  Created on   : Thu Jun 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Lohn & Sozialversicherung'))
@section('nav-title', __('Lohn & Sozialversicherung'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar
                        :subtitle="__('Organisations-Stammdaten, Mindestlohn und betroffene Mitarbeiter.')" />
    </x-slot:toolbar>

    {{-- Org-Stammdaten --}}
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h3 class="card-title text-base">{{ __('Betrieb & Finanzamt') }}</h3>
            <form method="POST" action="{{ route('payroll.settings.update') }}">
                @csrf @method('PUT')
                <x-form-group :legend="__('Sozialversicherung')" icon="health_and_safety" tone="primary" cols="2">
                    @php($country = old('country', $payroll['country'] ?? 'DE'))
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Land') }}</label>
                        <select name="country" class="select select-bordered w-full">
                            @foreach (['DE' => __('Deutschland'), 'AT' => __('Österreich'), 'CH' => __('Schweiz')] as $code => $label)
                                <option value="{{ $code }}" @selected($country === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-base-content/60">{{ __('Bestimmt u. a. die gesetzliche Mindestlohn-Historie.') }}</p>
                    </div>

                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Betriebsnummer (Knappschaft)') }}</label>
                        <input type="text" name="company_number" maxlength="32"
                               class="input input-bordered w-full @error('company_number') input-error @enderror"
                               value="{{ old('company_number', $payroll['company_number'] ?? '') }}">
                        @error('company_number')<p class="text-error text-sm">{{ $message }}</p>@enderror
                    </div>

                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Zuständiges Finanzamt') }}</label>
                        <input type="text" name="tax_office" maxlength="191"
                               class="input input-bordered w-full @error('tax_office') input-error @enderror"
                               value="{{ old('tax_office', $payroll['tax_office'] ?? '') }}">
                        @error('tax_office')<p class="text-error text-sm">{{ $message }}</p>@enderror
                    </div>
                </x-form-group>

                <x-form-group :legend="__('Steuerliche Identifikatoren')" icon="receipt_long" tone="ghost" cols="2"
                              :description="__('Wird auch für Rechnungen/Branding verwendet.')">
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Steuernummer') }}</label>
                        <input type="text" name="tax_number" maxlength="60"
                               class="input input-bordered w-full @error('tax_number') input-error @enderror"
                               value="{{ old('tax_number', $legal['tax_number'] ?? '') }}">
                        @error('tax_number')<p class="text-error text-sm">{{ $message }}</p>@enderror
                    </div>

                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('USt-IdNr.') }}</label>
                        <input type="text" name="vat_id" maxlength="60"
                               class="input input-bordered w-full @error('vat_id') input-error @enderror"
                               value="{{ old('vat_id', $legal['vat_id'] ?? '') }}">
                        @error('vat_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
                    </div>

                    <div class="fieldset md:col-span-2">
                        <label class="fieldset-label">{{ __('Handelsregister (HR-Nr.)') }}</label>
                        <input type="text" name="register" maxlength="200"
                               class="input input-bordered w-full @error('register') input-error @enderror"
                               value="{{ old('register', $legal['register'] ?? '') }}"
                               placeholder="{{ __('z. B. HRB 12345, Amtsgericht Musterstadt') }}">
                        @error('register')<p class="text-error text-sm">{{ $message }}</p>@enderror
                    </div>
                </x-form-group>

                <div class="mt-3 flex justify-end">
                    <x-button type="submit" tone="primary">{{ __('Speichern') }}</x-button>
                </div>
            </form>
        </div>
    </div>

    {{-- Mindestlohn-Historie --}}
    <div class="card mt-4 bg-base-100 shadow-sm">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <h3 class="card-title text-base">{{ __('Mindestlohn') }}</h3>
                <form method="POST" action="{{ route('payroll.minimum-wages.seed') }}"
                      data-confirm-dialog data-confirm-message="{{ __('Gesetzliche Mindestlohn-Historie für das Land der Organisation laden? Bestehende Sätze bleiben erhalten.') }}">
                    @csrf
                    <x-button type="submit" tone="ghost" icon="history">{{ __('Historie laden') }}</x-button>
                </form>
            </div>
            <p class="text-sm text-base-content/60">
                @if ($currentMinimum !== null)
                    {{ __('Aktuell: :amount € / Std.', ['amount' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($currentMinimum, 2, withThousandsSeparator: true)]) }}
                    @if ($minijobLimit !== null) · {{ __('Minijob-Grenze: :limit € / Monat', ['limit' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($minijobLimit, 0, withThousandsSeparator: true)]) }}@endif
                @else
                    {{ __('Noch kein Mindestlohn hinterlegt.') }}
                @endif
            </p>

            <form method="POST" action="{{ route('payroll.minimum-wages.store') }}" class="mt-2">
                @csrf
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Gültig ab') }}</label>
                        <input type="date" name="valid_from" required
                               class="input input-bordered w-full @error('valid_from') input-error @enderror"
                               value="{{ old('valid_from') }}">
                        @error('valid_from')<p class="text-error text-sm">{{ $message }}</p>@enderror
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Stundensatz (€)') }}</label>
                        <input type="number" step="0.01" min="0" name="hourly_amount" required
                               class="input input-bordered w-full @error('hourly_amount') input-error @enderror"
                               value="{{ old('hourly_amount') }}">
                        @error('hourly_amount')<p class="text-error text-sm">{{ $message }}</p>@enderror
                    </div>
                    <div class="fieldset sm:col-span-2">
                        <label class="fieldset-label">{{ __('Notiz') }}</label>
                        <div class="flex gap-2">
                            <input type="text" name="note" maxlength="191"
                                   class="input input-bordered w-full" value="{{ old('note') }}">
                            <x-button type="submit" tone="primary" class="shrink-0">{{ __('Hinzufügen') }}</x-button>
                        </div>
                    </div>
                </div>
            </form>

            <x-table table-sort="client" class="mt-2">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="date">{{ __('Gültig ab') }}</x-table.th>
                        <x-table.th sort type="number">{{ __('Stundensatz') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Notiz') }}</x-table.th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @forelse ($minimumWages as $mw)
                    <tr>
                        <td data-sort-value="{{ $mw->valid_from->format('Y-m-d') }}">{{ $mw->valid_from->fdate() }}</td>
                        <td class="tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($mw->hourly_amount?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }} €</td>
                        <td class="text-sm text-base-content/70">{{ $mw->note }}</td>
                        <td class="text-right">
                            <x-action-form :action="route('payroll.minimum-wages.destroy', $mw)" method="DELETE"
                                  :confirm="__('Mindestlohn-Satz entfernen?')">
                                <x-icon-btn type="submit" icon="delete" size="xs" tone="error" :title="__('Entfernen')" />
                            </x-action-form>
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="4"
                        icon='<span class="material-symbols-outlined" aria-hidden="true">payments</span>'
                        :title="__('Noch keine Mindestlohn-Sätze hinterlegt.')" compact />
                @endforelse
            </x-table>
        </div>
    </div>

    {{-- Eurostat-Referenz (monatlich, je Land) --}}
    <div class="card mt-4 bg-base-100 shadow-sm">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <h3 class="card-title text-base">{{ __('Eurostat-Referenz (monatlich)') }}</h3>
                <form method="POST" action="{{ route('payroll.references.import') }}"
                      data-confirm-dialog data-confirm-message="{{ __('Aktuelle EU-Mindestlöhne von Eurostat importieren?') }}">
                    @csrf
                    <x-button type="submit" tone="ghost" icon="cloud_download">{{ __('Eurostat-Import') }}</x-button>
                </form>
            </div>
            @if ($reference)
                <p class="text-sm text-base-content/70">
                    {{ __('Land :country: :amount :currency / Monat (Stand :date).', [
                        'country' => $reference->country,
                        'amount' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($reference->monthly_amount?->toFloat() ?? 0.0), 2, withThousandsSeparator: true),
                        'currency' => $reference->currency->value,
                        'date' => $reference->valid_from->fdate(),
                    ]) }}
                </p>
                <p class="text-xs text-base-content/50">{{ __('Monatlicher gesetzlicher Mindestlohn laut Eurostat – informativ, getrennt vom oben gepflegten Stundensatz.') }}</p>
            @else
                <p class="text-sm text-base-content/60">{{ __('Noch keine Eurostat-Daten für das Land der Organisation. Über „Eurostat-Import" laden.') }}</p>
            @endif
        </div>
    </div>

    {{-- Mitarbeiter unter Mindestlohn --}}
    <div class="card mt-4 bg-base-100 shadow-sm">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <h3 class="card-title text-base">{{ __('Mitarbeiter unter Mindestlohn') }}</h3>
                @if ($belowMinimum->isNotEmpty())
                    <form method="POST" action="{{ route('payroll.raise-to-minimum') }}"
                          data-confirm-dialog data-confirm-message="{{ __('Alle betroffenen Stundenlöhne auf den Mindestlohn anheben?') }}">
                        @csrf
                        <x-button type="submit" tone="primary">{{ __('Alle anheben') }}</x-button>
                    </form>
                @endif
            </div>

            @if ($belowMinimum->isEmpty())
                <x-empty-state compact
                    icon='<span class="material-symbols-outlined" aria-hidden="true">verified</span>'
                    :title="__('Kein Mitarbeiter liegt unter dem Mindestlohn.')" />
            @else
                <x-table table-sort="client" class="mt-1">
                    <x-slot:head>
                        <tr>
                            <x-table.th sort type="string">{{ __('Mitarbeiter') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('Beschäftigungsart') }}</x-table.th>
                            <x-table.th sort type="number">{{ __('Stundenlohn') }}</x-table.th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @foreach ($belowMinimum as $u)
                        <tr>
                            <td>{{ $u->name }}</td>
                            <td class="text-sm">{{ $u->employment_type?->label() ?? '—' }}</td>
                            <td class="tabular-nums text-warning">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($u->payroll_hourly_wage?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }} €</td>
                            <td class="text-right">
                                <x-action-form :action="route('payroll.raise-to-minimum')">
                                    <input type="hidden" name="user" value="{{ $u->sqid }}">
                                    <x-button type="submit" tone="ghost" size="xs">{{ __('Anheben') }}</x-button>
                                </x-action-form>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif
        </div>
    </div>
</x-page-shell>
@endsection
