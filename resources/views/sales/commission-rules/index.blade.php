{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Provisionsregeln (Feature 146): Satz je Lead-Quelle, Produktgruppe oder
  Vertriebsperson. Je Beleg gewinnt genau eine Regel.
--}}
@extends('layouts.app')
@section('title', __('commission.page.rules'))
@section('nav-title', __('commission.page.rules'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('commission.subtitle.rules')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('commission-rules.create')"
                        show-label>{{ __('commission.action.create_rule') }}</x-icon-btn>
        @endif
        <x-icon-btn icon="receipt_long" tone="ghost" size="sm"
                    :href="route('commissions.index')"
                    show-label>{{ __('commission.action.to_commissions') }}</x-icon-btn>
        <x-icon-btn icon="event_repeat" tone="ghost" size="sm"
                    :href="route('commission-runs.index')"
                    show-label>{{ __('commission.action.to_runs') }}</x-icon-btn>
    </x-slot:actions>

    <x-table scroll="flex" table-sort="client">
        <x-slot:head>
            <tr>
                <x-table.th sort type="string" default>{{ __('commission.field.name') }}</x-table.th>
                <x-table.th sort type="string">{{ __('commission.field.scope') }}</x-table.th>
                <x-table.th sort type="string">{{ __('commission.field.scope_value') }}</x-table.th>
                <x-table.th sort type="number" align="right">{{ __('commission.field.rate_percent') }}</x-table.th>
                <x-table.th sort type="string">{{ __('commission.field.validity') }}</x-table.th>
                <x-table.th sort type="number" align="center">{{ __('commission.field.priority') }}</x-table.th>
                <x-table.th sort type="string" align="center">{{ __('commission.field.is_active') }}</x-table.th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($rules as $rule)
            <tr class="hover">
                <td class="font-medium">{{ $rule->name }}</td>
                <td class="text-sm">
                    <x-status-badge tone="info" size="sm" outline>{{ $rule->scope->label() }}</x-status-badge>
                </td>
                <td class="text-sm text-base-content/70">{{ $rule->user?->name ?? $rule->scope_value ?? '–' }}</td>
                <td class="text-right font-mono text-sm">{{ $rule->rate_percent?->format() ?? '0,00' }} %</td>
                <td class="text-sm">
                    {{ $rule->valid_from?->format('d.m.Y') ?? '–' }} – {{ $rule->valid_to?->format('d.m.Y') ?? '–' }}
                </td>
                <td class="text-center text-sm">{{ $rule->priority }}</td>
                <td class="text-center">
                    <x-status-badge :tone="$rule->is_active ? 'success' : 'ghost'" size="sm">
                        {{ $rule->is_active ? __('Ja') : __('Nein') }}
                    </x-status-badge>
                </td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        @if ($canManage)
                            <x-icon-btn icon="edit" tone="outline" size="xs"
                                        data-entry-modal-trigger
                                        :href="route('commission-rules.edit', $rule)"
                                        :label="__('commission.action.edit')" />
                            <x-action-form :action="route('commission-rules.destroy', $rule)" method="DELETE"
                                           :confirm="__('commission.confirm.delete_rule')"
                                           confirm-icon="delete" confirm-tone="error"
                                           :confirm-label="__('commission.action.delete')">
                                <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('commission.action.delete')" />
                            </x-action-form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="percent" :colspan="8" :title="__('commission.empty.rules')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$rules" standing />
</x-index-page>
@endsection
