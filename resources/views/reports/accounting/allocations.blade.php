{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : allocations.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Umlageschlüssel je Geschäftsjahr (MVP-982): Vorkostenstelle → Anteile der
  Endkostenstellen. Wirkt nur in der BWA „nach Umlage“.
--}}
@extends('layouts.app')
@section('title', __('accounting.allocation.title'))
@section('nav-title', __('accounting.allocation.title'))
@section('content')
    <x-index-page :subtitle="__('accounting.allocation.subtitle', ['year' => $year])"
                  back-route="reports.accounting.budget.index" :back-label="__('accounting.budget.title')">
        <x-slot:actions>
            @if ($canEdit)
                <x-icon-btn icon="add" tone="primary" size="sm" show-label data-entry-modal-trigger
                            :href="route('reports.accounting.allocations.create', ['year' => $year])" :label="__('accounting.allocation.action.add')" />
            @endif
        </x-slot:actions>
        <x-filter-bar :action="route('reports.accounting.allocations.index')" :reset="route('reports.accounting.allocations.index')">
            <x-filter-field :label="__('accounting.budget.filter.year')" for="allocation-year">
                <input id="allocation-year" type="number" name="year" min="2000" max="2100" value="{{ $year }}" class="input input-sm input-bordered w-28 shrink-0">
            </x-filter-field>
        </x-filter-bar>

        <p class="text-sm text-muted">{{ __('accounting.allocation.hint') }}</p>

        @forelse ($keys as $sourceKeys)
            @php($source = $sourceKeys->first()->source)
            <x-card :title="$source->code . ' · ' . $source->label" icon="call_split"
                    :subtitle="__('accounting.allocation.total', ['percent' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $sourceKeys->sum(fn ($key) => (float) $key->share_percent), 2)])">
                @foreach ($sourceKeys as $key)
                    <div class="flex items-center justify-between gap-2 py-1 text-sm">
                        <span>→ {{ $key->target->code }} · {{ $key->target->label }} <span class="font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $key->share_percent, 2) }} %</span></span>
                        @if ($canEdit)
                            <x-action-form :action="route('reports.accounting.allocations.destroy', $key)" method="DELETE"
                                           :confirm="__('accounting.allocation.confirm.remove')" :confirm-label="__('Entfernen')">
                                <x-icon-btn type="submit" icon="delete" size="sm" tone="error" :label="__('Entfernen')" />
                            </x-action-form>
                        @endif
                    </div>
                @endforeach
            </x-card>
        @empty
            <x-empty-state framed icon="call_split" :title="__('accounting.allocation.empty')" :message="__('accounting.allocation.hint')" />
        @endforelse
    </x-index-page>
@endsection
