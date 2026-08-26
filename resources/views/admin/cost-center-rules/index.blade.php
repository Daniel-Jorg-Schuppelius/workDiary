{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('costcenter.title.rules'))
@section('nav-title', __('costcenter.title.rules'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('costcenter.title.rules_subtitle')">
    <x-slot:actions>
        @if ($canManage ?? false)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('admin.cost-center-rules.create')"
                        show-label>{{ __('costcenter.action.create') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <div role="alert" class="alert alert-info alert-soft">
        <x-icon name="info" />
        <div>
            <h3 class="font-semibold">{{ __('costcenter.title.rules_help') }}</h3>
            <div class="text-sm">{{ __('costcenter.title.rules_help_text') }}</div>
        </div>
    </div>

    <x-table scroll="flex" :pinRows="true">
        <x-slot:head>
            <tr>
                <th>{{ __('costcenter.field.source') }}</th>
                <th>{{ __('costcenter.field.cost_center') }}</th>
                <th class="text-right">{{ __('costcenter.field.priority') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($rules as $rule)
            @php /** @var \App\Models\CostCenterRule $rule */ @endphp
            <tr>
                <td>
                    @if ($rule->user_id !== null)
                        <x-status-badge size="xs" tone="info">
                            <x-icon name="person" class="text-sm" />
                            {{ $rule->user?->name ?? '—' }}
                        </x-status-badge>
                    @elseif ($rule->team_id !== null)
                        <x-status-badge size="xs" tone="warning">
                            <x-icon name="groups" class="text-sm" />
                            {{ $rule->team?->name ?? '—' }}
                        </x-status-badge>
                    @else
                        <x-status-badge size="xs" tone="neutral">
                            <x-icon name="domain" class="text-sm" />
                            {{ __('costcenter.field.source_default') }}
                        </x-status-badge>
                    @endif
                </td>
                <td class="font-mono text-sm">
                    {{ $rule->effectiveCode() }}@if ($rule->costCenter !== null && $rule->costCenter->label !== $rule->costCenter->code)
                        <span class="font-sans text-xs text-muted">— {{ $rule->costCenter->label }}</span>
                    @endif
                </td>
                <td class="text-right tabular-nums">{{ $rule->priority }}</td>
                <td class="text-right">
                    @if ($canManage ?? false)
                        <x-icon-btn icon="edit" tone="ghost" size="xs"
                                    data-entry-modal-trigger
                                    :href="route('admin.cost-center-rules.edit', $rule)"
                                    :label="__('costcenter.action.edit')" />
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="4" icon="account_balance" :label="__('costcenter.title.empty')" />
        @endforelse
    </x-table>
</x-index-page>
@endsection
