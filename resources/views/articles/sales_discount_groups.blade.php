{{--
  Created on   : Sat Aug 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : sales_discount_groups.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('article.discount_group.title'))
@section('nav-title', __('article.title'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        <x-validation-errors first />

        <x-page-toolbar :subtitle="__('article.discount_group.hint')" />

        <x-card>

            <form method="POST" action="{{ route('articles.sales-discount-groups.store') }}" class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                @csrf
                <x-input-field name="code" :label="__('article.discount_group.col.code')" required maxlength="4" />
                <x-select-field name="kind" :label="__('article.discount_group.col.kind')" required>
                    <option value="discount">{{ __('article.discount_group.kind.discount') }}</option>
                    <option value="factor">{{ __('article.discount_group.kind.factor') }}</option>
                    <option value="surcharge">{{ __('article.discount_group.kind.surcharge') }}</option>
                </x-select-field>
                <x-input-field name="value" type="number" step="0.0001" min="0" :label="__('article.discount_group.col.value')" required />
                <x-input-field name="label" :label="__('article.discount_group.col.label')" maxlength="191" />
                <div class="flex items-end">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('article.discount_group.action.add') }}</button>
                </div>
            </form>

            <x-table :bare="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('article.discount_group.col.code') }}</th>
                        <th>{{ __('article.discount_group.col.kind') }}</th>
                        <th class="text-right">{{ __('article.discount_group.col.value') }}</th>
                        <th>{{ __('article.discount_group.col.label') }}</th>
                        <th class="text-right">{{ __('article.discount_group.col.articles') }}</th>
                        <th class="text-right"></th>
                    </tr>
                </x-slot:head>
                        @forelse ($groups as $group)
                            <tr class="hover">
                                <td class="font-mono">{{ $group->code }}</td>
                                <td>{{ __('article.discount_group.kind.' . $group->kind) }}</td>
                                <td class="text-right">{{ rtrim(rtrim($group->value, '0'), '.') }}{{ $group->kind === 'factor' ? '' : ' %' }}</td>
                                <td>{{ $group->label }}</td>
                                <td class="text-right">{{ $group->articles_count }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('articles.sales-discount-groups.destroy', $group) }}"
                                          data-confirm-dialog
                                          data-confirm-title="{{ __('article.discount_group.action.delete') }}"
                                          data-confirm-message="{{ __('article.discount_group.confirm_delete') }}"
                                          data-confirm-tone="error">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('article.discount_group.action.delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <x-table.empty :colspan="6" :title="__('article.discount_group.empty')" compact />
                        @endforelse
            </x-table>
        </x-card>

        {{-- MVP-567: kundenindividuelle Overrides je Gruppe --}}
        <x-card>
            <h2 class="mb-1 font-semibold">{{ __('article.discount_group.override.title') }}</h2>
            <p class="mb-4 text-sm opacity-70">{{ __('article.discount_group.override.hint') }}</p>

            <form method="POST" action="{{ route('articles.sales-discount-groups.overrides.store') }}" class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                @csrf
                <x-select-field name="sales_discount_group_id" :label="__('article.discount_group.col.code')" required>
                    @foreach ($groups as $group)
                        <option value="{{ $group->sqid }}">{{ $group->code }}{{ $group->label ? ' — ' . $group->label : '' }}</option>
                    @endforeach
                </x-select-field>
                <x-select-field name="customer_id" :label="__('article.discount_group.override.customer')" required>
                    @foreach ($customers ?? [] as $customer)
                        <option value="{{ $customer->sqid }}">{{ $customer->displayLabel() }} ({{ $customer->number }})</option>
                    @endforeach
                </x-select-field>
                <x-select-field name="kind" :label="__('article.discount_group.col.kind')" required>
                    <option value="discount">{{ __('article.discount_group.kind.discount') }}</option>
                    <option value="factor">{{ __('article.discount_group.kind.factor') }}</option>
                    <option value="surcharge">{{ __('article.discount_group.kind.surcharge') }}</option>
                </x-select-field>
                <x-input-field name="value" type="number" step="0.0001" min="0" :label="__('article.discount_group.col.value')" required />
                <div class="flex items-end">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('article.discount_group.action.add') }}</button>
                </div>
            </form>

            <x-table :bare="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('article.discount_group.col.code') }}</th>
                        <th>{{ __('article.discount_group.override.customer') }}</th>
                        <th>{{ __('article.discount_group.col.kind') }}</th>
                        <th class="text-right">{{ __('article.discount_group.col.value') }}</th>
                        <th class="text-right"></th>
                    </tr>
                </x-slot:head>
                        @forelse ($overrides ?? [] as $override)
                            <tr class="hover">
                                <td class="font-mono">{{ $override->group?->code }}</td>
                                <td>{{ $override->customer?->displayLabel() }} ({{ $override->customer?->number }})</td>
                                <td>{{ __('article.discount_group.kind.' . $override->kind) }}</td>
                                <td class="text-right">{{ rtrim(rtrim($override->value, '0'), '.') }}{{ $override->kind === 'factor' ? '' : ' %' }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('articles.sales-discount-groups.overrides.destroy', $override) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('article.discount_group.action.delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <x-table.empty :colspan="5" :title="__('article.discount_group.override.empty')" compact />
                        @endforelse
            </x-table>
        </x-card>
    </div>
</x-page-shell>
@endsection
