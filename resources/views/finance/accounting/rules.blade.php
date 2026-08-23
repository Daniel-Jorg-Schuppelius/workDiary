{{--
  Created on   : Fri Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : rules.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Buchungsregeln (Feature 125, MVP-673): Quelle + Rolle → Konto. Eine
  Änderung mit neuem Stichtag erzeugt eine Folgefassung, damit Altbuchungen
  ihre Regelversion behalten.
--}}

@extends('layouts.app')

@section('title', __('accounting.rules.title'))
@section('nav-title', __('accounting.rules.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('accounting.rules.subtitle')">
        <x-slot:actions>
            @if ($canConfigure)
                <x-icon-btn icon="add" size="sm" tone="primary"
                            data-entry-modal-trigger
                            :href="route('finance.accounting.rules.create')"
                            :label="__('accounting.rules.action.add')" />
            @endif
        </x-slot:actions>

        <x-table scroll="flex" :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('accounting.inbox.column.kind') }}</th>
                    <th>{{ __('accounting.rules.column.role') }}</th>
                    <th>{{ __('accounting.rules.column.match') }}</th>
                    <th>{{ __('accounting.ledger.column.account') }}</th>
                    <th>{{ __('accounting.rules.column.validity') }}</th>
                    <th>{{ __('accounting.rules.column.priority') }}</th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($rules as $rule)
                <tr class="hover {{ $rule->is_active ? '' : 'opacity-60' }}">
                    <td><x-status-badge :tone="$rule->source_kind->tone()">{{ $rule->source_kind->label() }}</x-status-badge></td>
                    <td>{{ $rule->role->label() }}</td>
                    <td class="text-xs font-mono">
                        @if ($rule->match_criteria)
                            @foreach ($rule->match_criteria as $key => $value)
                                <div>{{ $key }} = {{ $value }}</div>
                            @endforeach
                        @else
                            <span class="opacity-60">{{ __('accounting.rules.fallback') }}</span>
                        @endif
                    </td>
                    <td>{{ $rule->account?->displayLabel() ?? '—' }}</td>
                    <td class="text-xs">
                        {{ $rule->valid_from->fdate() }} – {{ $rule->valid_to?->fdate() ?? __('accounting.ledger.open_ended') }}
                        <span class="ml-1 opacity-60">v{{ $rule->version }}</span>
                    </td>
                    <td class="font-mono">{{ $rule->priority }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @if ($canConfigure)
                                <x-icon-btn icon="edit" size="xs" tone="ghost"
                                            data-entry-modal-trigger
                                            :href="route('finance.accounting.rules.edit', $rule)"
                                            :label="__('Bearbeiten')" />
                                @if ($rule->is_active)
                                    <x-action-form :action="route('finance.accounting.rules.destroy', $rule)"
                                                   method="DELETE"
                                                   :confirm="__('accounting.rules.confirm.deactivate')">
                                        <x-icon-btn icon="block" size="xs" tone="ghost" type="submit"
                                                    :label="__('accounting.ledger.action.deactivate')" />
                                    </x-action-form>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty-state icon="rule" :title="__('accounting.rules.empty')" /></td></tr>
            @endforelse
        </x-table>
    </x-index-page>
@endsection
