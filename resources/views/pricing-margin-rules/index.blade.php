{{--
  Created on   : Sun Jun 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('procurement.margin.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('procurement.margin.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('procurement.margin.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="fact_check" size="sm" :href="route('pricing-margin-rules.approvals')" show-label>
            {{ __('procurement.approval.title') }}@if ($openApprovals > 0) <span class="badge badge-warning badge-sm">{{ $openApprovals }}</span>@endif
        </x-icon-btn>
        <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                    :href="route('pricing-margin-rules.create')" show-label>{{ __('procurement.margin.action.new_rule') }}</x-icon-btn>
    </x-slot:actions>

    <x-card>
        <form method="POST" action="{{ route('pricing-margin-rules.approval-mode') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="fieldset">
                <label for="mode" class="fieldset-label">{{ __('procurement.approval.mode.label') }}</label>
                <select id="mode" name="mode" class="select select-sm select-bordered">
                    <option value="direct" @selected($approvalMode === 'direct')>{{ __('procurement.approval.mode.direct') }}</option>
                    <option value="four_eyes" @selected($approvalMode === 'four_eyes')>{{ __('procurement.approval.mode.four_eyes') }}</option>
                </select>
            </div>
            <button type="submit" class="btn btn-sm">{{ __('Speichern') }}</button>
            <p class="text-xs opacity-60 basis-full">{{ __('procurement.approval.mode.hint') }}</p>
        </form>
    </x-card>

    @if ($rules->total() === 0)
        <x-empty-state framed icon="percent"
                       :title="__('procurement.margin.empty')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('procurement.margin.col.name') }}</th>
                    <th>{{ __('procurement.margin.col.scope') }}</th>
                    <th class="text-right">{{ __('procurement.margin.col.calc') }}</th>
                    <th class="text-right">{{ __('procurement.margin.col.min') }}</th>
                    <th>{{ __('procurement.margin.col.rounding') }}</th>
                    <th class="text-right">{{ __('procurement.margin.col.priority') }}</th>
                    <th class="text-right">{{ __('procurement.catalog.col.actions') }}</th>
                </tr>
            </x-slot:head>
                @foreach ($rules as $rule)
                    <tr @class(['hover', 'opacity-50' => ! $rule->active])>
                        <td class="font-medium">{{ $rule->name }}</td>
                        <td class="text-sm">
                            {{ $rule->supplier?->name ?: __('procurement.margin.scope_all_suppliers') }}
                            @if ($rule->category)<span class="opacity-60">· {{ $rule->category }}</span>@endif
                        </td>
                        <td class="text-right tabular-nums">
                            @if ($rule->target_margin !== null)
                                {{ __('procurement.margin.target') }} {{ rtrim(rtrim($rule->target_margin, '0'), '.') }} %
                            @elseif ($rule->markup_percent !== null)
                                {{ __('procurement.margin.markup') }} {{ rtrim(rtrim($rule->markup_percent, '0'), '.') }} %
                            @else — @endif
                        </td>
                        <td class="text-right tabular-nums text-sm opacity-70">
                            {{ $rule->min_margin !== null ? rtrim(rtrim($rule->min_margin, '0'), '.') . ' %' : '—' }}
                        </td>
                        <td class="text-sm">{{ $rule->rounding->label() }}</td>
                        <td class="text-right tabular-nums">{{ $rule->priority }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('pricing-margin-rules.destroy', $rule) }}"
                                  data-confirm-dialog data-confirm-message="{{ __('procurement.margin.confirm_delete') }}">
                                @csrf @method('DELETE')
                                <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('Löschen')" />
                            </form>
                        </td>
                    </tr>
                @endforeach
        </x-table>
        <x-pagination :paginator="$rules" standing />
    @endif
</x-index-page>
@endsection
