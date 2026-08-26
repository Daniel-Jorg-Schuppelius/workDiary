{{--
  Created on   : Sun Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  License      : AGPL-3.0-or-later

  Buchhaltungs-Konfiguration für den DATEV-Buchungsstapel (Feature 045,
  Priorität 2): Berater-/Mandantennummer, Kontenrahmen, Sachkonten,
  Debitoren-Nummernkreis, Steuerschlüssel-Mapping und Festschreibekennzeichen.
  Recht: finance.config.
--}}

@extends('layouts.app')

@section('title', __('finance.datev.config.title'))
@section('nav-title', __('finance.datev.menu'))

@section('content')
    <x-index-page :subtitle="__('finance.datev.config.subtitle')">
        <form method="POST" action="{{ route('finance.datev.config.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-form-group :legend="__('finance.datev.config.client_group')" icon="badge" tone="info" cols="2" compact>
                <x-input-field name="datev[advisor_number]"
                               :label="__('finance.datev.config.advisor_number')"
                               type="number"
                               value="{{ old('datev.advisor_number', data_get($stored, 'advisor_number', '')) }}"
                               min="1"
                               max="9999999" />
                <x-input-field name="datev[client_number]"
                               :label="__('finance.datev.config.client_number')"
                               type="number"
                               value="{{ old('datev.client_number', data_get($stored, 'client_number', '')) }}"
                               min="1"
                               max="99999" />
            </x-form-group>

            <x-form-group :legend="__('finance.datev.config.accounts_group')" icon="account_tree" tone="success" cols="2" compact>
                <x-select-field name="datev[skr]" :label="__('finance.datev.config.skr')">
                    @foreach ($chartOptions as $chart)
                        <option value="{{ $chart->value }}"
                            @selected(old('datev.skr', data_get($stored, 'skr', $config->skr->value)) === $chart->value)>
                            {{ $chart->label() }}
                        </option>
                    @endforeach
                </x-select-field>
                <x-input-field name="datev[account_length]"
                               :label="__('finance.datev.config.account_length')"
                               type="number"
                               value="{{ old('datev.account_length', data_get($stored, 'account_length', $config->accountLength)) }}"
                               min="4"
                               max="8" />
                <x-input-field name="datev[revenue_account]"
                               :label="__('finance.datev.config.revenue_account')"
                               type="text"
                               value="{{ old('datev.revenue_account', data_get($stored, 'revenue_account', '')) }}"
                               maxlength="12"
                               placeholder="{{ $config->skr->defaultRevenueAccount() }}" />
                <x-input-field name="datev[revenue_account_tax_free]"
                               :label="__('finance.datev.config.revenue_account_tax_free')"
                               type="text"
                               value="{{ old('datev.revenue_account_tax_free', data_get($stored, 'revenue_account_tax_free', '')) }}"
                               maxlength="12"
                               placeholder="{{ $config->skr->defaultTaxFreeRevenueAccount() }}" />
                <x-input-field name="datev[debtor_base]"
                               :label="__('finance.datev.config.debtor_base')"
                               type="number"
                               value="{{ old('datev.debtor_base', data_get($stored, 'debtor_base', $config->debtorBase)) }}"
                               :hint="__('finance.datev.config.debtor_base_hint')"
                               min="1"
                               max="99999999" />
            </x-form-group>

            <x-form-group :legend="__('finance.datev.config.tax_group')" icon="percent" tone="warning" cols="3" compact>
                <x-input-field name="datev[tax_keys][19.00]"
                               :label="__('finance.datev.config.tax_key_19')"
                               type="text"
                               value="{{ old('datev.tax_keys.19\.00', data_get($stored, 'tax_keys.19\.00', $config->taxKeyFor(19.0))) }}"
                               maxlength="4" />
                <x-input-field name="datev[tax_keys][7.00]"
                               :label="__('finance.datev.config.tax_key_7')"
                               type="text"
                               value="{{ old('datev.tax_keys.7\.00', data_get($stored, 'tax_keys.7\.00', $config->taxKeyFor(7.0))) }}"
                               maxlength="4" />
                <x-input-field name="datev[tax_keys][0.00]"
                               :label="__('finance.datev.config.tax_key_0')"
                               type="text"
                               value="{{ old('datev.tax_keys.0\.00', data_get($stored, 'tax_keys.0\.00', $config->taxKeyFor(0.0))) }}"
                               maxlength="4" />
            </x-form-group>

            {{-- MVP-334: differenzierte Aufwands-/Vorsteuerkonten je Spesenkategorie. --}}
            @if ($expenseCategories->isNotEmpty())
                <x-form-group :legend="__('finance.datev.config.expense_group')" icon="receipt" tone="error" cols="1" compact>
                    <p class="text-xs text-muted">{{ __('finance.datev.config.expense_group_hint') }}</p>
                    <x-table>
                        <x-slot:head>
                                <tr>
                                    <th>{{ __('finance.datev.config.expense_category') }}</th>
                                    <th>{{ __('finance.datev.config.expense_account') }}</th>
                                    <th>{{ __('finance.datev.config.expense_tax_key') }}</th>
                                </tr>
                        </x-slot:head>
                                @foreach ($expenseCategories as $category)
                                    <tr>
                                        <td>{{ $category->label }}</td>
                                        <td>
                                            <input type="text" maxlength="12"
                                                   name="datev[expense_accounts][{{ $category->sqid }}][account]"
                                                   value="{{ old('datev.expense_accounts.' . $category->sqid . '.account', data_get($config->expenseAccounts, $category->id . '.account', '')) }}"
                                                   class="input input-bordered input-sm w-full">
                                        </td>
                                        <td>
                                            <input type="text" maxlength="4"
                                                   name="datev[expense_accounts][{{ $category->sqid }}][tax_key]"
                                                   value="{{ old('datev.expense_accounts.' . $category->sqid . '.tax_key', data_get($config->expenseAccounts, $category->id . '.tax_key', '')) }}"
                                                   class="input input-bordered input-sm w-full">
                                        </td>
                                    </tr>
                                @endforeach
                    </x-table>
                </x-form-group>
            @endif

            <x-form-group :legend="__('finance.datev.config.export_group')" icon="tune" tone="ghost" cols="2" compact>
                <div class="fieldset">
                    <label class="label cursor-pointer justify-start gap-3">
                        <input type="hidden" name="datev[finalize]" value="0">
                        <input type="checkbox" name="datev[finalize]" value="1"
                               @checked(old('datev.finalize', data_get($stored, 'finalize', $config->finalize))) class="checkbox checkbox-sm">
                        <span class="label-text">{{ __('finance.datev.config.finalize') }}</span>
                    </label>
                    <p class="mt-1 text-xs text-muted">{{ __('finance.datev.config.finalize_hint') }}</p>
                </div>
                <x-select-field name="datev[encoding]"
                                :label="__('finance.datev.config.encoding')"
                                :hint="__('finance.datev.config.encoding_hint')">
                    @foreach (['ISO-8859-1', 'UTF-8'] as $enc)
                        <option value="{{ $enc }}" @selected(old('datev.encoding', data_get($stored, 'encoding', $config->encoding)) === $enc)>{{ $enc }}</option>
                    @endforeach
                </x-select-field>
            </x-form-group>

            <x-validation-errors />

            <div class="flex justify-end gap-2">
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('finance.datev.index')" show-label>{{ __('Zurück') }}</x-icon-btn>
                <x-button type="submit" tone="primary">{{ __('finance.datev.action.save_config') }}</x-button>
            </div>
        </form>
    </x-index-page>
@endsection
