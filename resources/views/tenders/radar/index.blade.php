{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Bekanntmachungs-Radar') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Bekanntmachungs-Radar'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $matches */
    /** @var array<string, int> $counts */
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('Öffentliche Bekanntmachungen des Bundes, gefiltert nach den eigenen Suchprofilen.')">
    <x-slot:actions>
        <x-icon-btn icon="tune" size="sm" :href="route('tender-radar.profiles')" show-label>{{ __('Suchprofile') }}</x-icon-btn>
    </x-slot:actions>

    <x-filter-bar :action="route('tender-radar.index')" :reset="route('tender-radar.index')">
        <x-filter-field :label="__('Ansicht')" for="radar-state" class="shrink-0">
            <select id="radar-state" name="state" class="select select-sm select-bordered w-56" aria-label="{{ __('Ansicht') }}">
                <option value="new" @selected($state === 'new')>{{ __('Neu (:count)', ['count' => $counts['new'] ?? 0]) }}</option>
                <option value="muted" @selected($state === 'muted')>{{ __('Ausgeblendet (:count)', ['count' => $counts['muted'] ?? 0]) }}</option>
                <option value="converted" @selected($state === 'converted')>{{ __('Übernommen (:count)', ['count' => $counts['converted'] ?? 0]) }}</option>
            </select>
        </x-filter-field>
    </x-filter-bar>

    {{-- Der Hinweis auf das fehlende Profil verdrängt keine vorhandenen Treffer:
         ein gelöschtes Profil löscht seine Funde nicht. --}}
    @if ($profileCount === 0 && $matches->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">radar</span>'
                       :title="__('Noch kein Suchprofil angelegt')"
                       :message="__('Ein Suchprofil sagt, welche Leistungen (CPV) in welcher Region (NUTS) infrage kommen. Ohne Profil sucht der Radar nichts.')">
            @if ($canManage)
                <x-icon-btn icon="add" tone="primary" size="sm" :href="route('tender-radar.profiles.create')"
                            data-entry-modal-trigger show-label>{{ __('Suchprofil anlegen') }}</x-icon-btn>
            @endif
        </x-empty-state>
    @elseif ($matches->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">radar</span>'
                       :title="__('Keine Bekanntmachungen in dieser Ansicht')"
                       :message="__('Der Abruf läuft täglich und holt den Vortag — ein Tag ist erst am Folgetag vollständig.')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Bekanntmachung') }}</th>
                    <th>{{ __('Vergabestelle') }}</th>
                    <th>{{ __('CPV / Region') }}</th>
                    <th class="text-right">{{ __('Auftragswert') }}</th>
                    <th>{{ __('Abgabefrist') }}</th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @foreach ($matches as $match)
                @php $notice = $match->notice; @endphp
                <tr class="hover">
                    <td class="font-medium">
                        {{ $notice?->title ?? '—' }}
                        <div class="text-xs text-base-content/60">
                            @if ($match->profile)
                                {{ $match->profile->name }}
                            @endif
                            @if ($notice?->published_on)
                                · {{ $notice->published_on->format('d.m.Y') }}
                            @endif
                        </div>
                    </td>
                    <td class="text-base-content/70">{{ $notice?->buyer_name ?? '—' }}</td>
                    <td class="text-base-content/70">
                        <div class="font-mono text-xs">{{ implode(', ', array_slice($notice?->cpv_codes ?? [], 0, 3)) ?: '—' }}</div>
                        <div class="font-mono text-xs opacity-70">{{ $notice?->nuts_code ?? '—' }}</div>
                    </td>
                    <td class="text-right tabular-nums">
                        @if ($notice?->estimated_value !== null)
                            {{ number_format((float) $notice->estimated_value, 2, ',', '.') }} {{ $notice->currency ?? '' }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-base-content/70 tabular-nums">
                        @if ($notice?->submission_deadline)
                            {{-- Eine abgelaufene Frist ist kein Vorgang mehr: sichtbar machen, nicht verstecken. --}}
                            <span @class(['text-error font-medium' => $notice->submission_deadline->isPast()])>
                                {{ $notice->submission_deadline->format('d.m.Y') }}
                            </span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex items-center justify-end gap-1">
                            @if ($notice?->url)
                                <x-icon-btn icon="open_in_new" size="sm" :href="$notice->url" target="_blank"
                                            rel="noopener" :title="__('Bekanntmachung öffnen')" />
                            @endif
                            @if ($canManage)
                                @if ($match->state === 'converted')
                                    @if ($match->opportunity)
                                        <x-icon-btn icon="gavel" size="sm" :href="route('tenders.show', $match->opportunity)"
                                                    :title="__('Vergabevorgang öffnen')" />
                                    @endif
                                @elseif ($match->state === 'muted')
                                    <x-action-form :action="route('tender-radar.restore', $match)">
                                        <x-icon-btn icon="visibility" size="sm" type="submit" :title="__('Wieder einblenden')" />
                                    </x-action-form>
                                @else
                                    <x-action-form :action="route('tender-radar.convert', $match)">
                                        <x-icon-btn icon="gavel" tone="primary" size="sm" type="submit" show-label
                                                    :title="__('Als Vergabevorgang übernehmen')">{{ __('Übernehmen') }}</x-icon-btn>
                                    </x-action-form>
                                    <x-action-form :action="route('tender-radar.mute', $match)">
                                        <x-icon-btn icon="visibility_off" size="sm" type="submit" :title="__('Ausblenden')" />
                                    </x-action-form>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$matches" standing />
    @endif
</x-index-page>
@endsection
