{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : profiles.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Suchprofile') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Suchprofile'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Tenders\TenderFilterProfile> $profiles */
    $asText = static fn (?array $values): string => implode(', ', $values ?? []);
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('Wonach der Radar in den Bekanntmachungen des Bundes sucht.')">
    <x-slot:actions>
        <x-icon-btn icon="radar" size="sm" :href="route('tender-radar.index')" show-label>{{ __('Treffer') }}</x-icon-btn>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                        :href="route('tender-radar.profiles.create')" show-label>{{ __('Suchprofil anlegen') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    @if ($profiles->isEmpty())
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">tune</span>'
                       :title="__('Noch kein Suchprofil angelegt')"
                       :message="__('Ein Suchprofil sagt, welche Leistungen (CPV) in welcher Region (NUTS) infrage kommen. Ohne Profil sucht der Radar nichts.')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Bezeichnung') }}</th>
                    <th>{{ __('CPV-Codes') }}</th>
                    <th>{{ __('Regionen (NUTS)') }}</th>
                    <th>{{ __('Stichwörter') }}</th>
                    <th class="text-right">{{ __('Auftragswert') }}</th>
                    <th class="text-right">{{ __('Treffer') }}</th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @foreach ($profiles as $profile)
                <tr class="hover">
                    <td class="font-medium">
                        {{ $profile->name }}
                        @unless ($profile->active)
                            <x-status-badge tone="ghost" size="xs">{{ __('Inaktiv') }}</x-status-badge>
                        @endunless
                    </td>
                    <td class="font-mono text-xs text-base-content/70">{{ $asText($profile->cpv_codes) ?: '—' }}</td>
                    <td class="font-mono text-xs text-base-content/70">{{ $asText($profile->nuts_codes) ?: '—' }}</td>
                    <td class="text-base-content/70">
                        <div class="text-xs">{{ $asText($profile->keywords) ?: '—' }}</div>
                        @if ($profile->excluded_keywords)
                            <div class="text-xs text-error/80">− {{ $asText($profile->excluded_keywords) }}</div>
                        @endif
                    </td>
                    <td class="text-right text-xs tabular-nums text-base-content/70">
                        @if ($profile->min_value !== null || $profile->max_value !== null)
                            {{ $profile->min_value !== null ? number_format((float) $profile->min_value, 0, ',', '.') : '' }}
                            –
                            {{ $profile->max_value !== null ? number_format((float) $profile->max_value, 0, ',', '.') : '' }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right tabular-nums">{{ $profile->matches_count }}</td>
                    <td class="text-right">
                        @if ($canManage)
                            <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger
                                        :href="route('tender-radar.profiles.edit', $profile)" />
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-index-page>
@endsection
