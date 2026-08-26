{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Zutrittsmedien'))
@section('nav-title', __('Zutrittsmedien'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    use App\Enums\Access\{AccessMediumStatus, AccessMediumType};
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $media */
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('Transponder, Karten und Codes als verwalteter Bestand — jedes Medium hat jederzeit genau einen Status und einen belegten Verbleib.')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                        :href="route('access-media.create')" show-label>{{ __('Medium anlegen') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('access-media.index')" :reset="route('access-media.index')">
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
               class="input input-sm input-bordered w-56 shrink-0"
               placeholder="{{ __('Bezeichnung, Inhaber, Anlage, Suffix') }}" aria-label="{{ __('Suche') }}">
        <select name="status" class="select select-sm select-bordered w-52 shrink-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Alle Status') }}</option>
            @foreach (AccessMediumStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>
                    {{ $status->label() }} ({{ $counts[$status->value] ?? 0 }})
                </option>
            @endforeach
        </select>
        <select name="type" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Typ') }}">
            <option value="">{{ __('Alle Typen') }}</option>
            @foreach (AccessMediumType::cases() as $type)
                <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if ($media->isEmpty())
        <x-empty-state framed icon="key"
                       :title="__('Keine Zutrittsmedien im gewählten Ausschnitt.')"
                       :message="__('Der Bestand ergänzt die physische Schlüsselübergabe um Transponder, Karten und Codes — inklusive Verlust- und Sperr-Nachweis.')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Medium') }}</th>
                    <th>{{ __('Typ') }}</th>
                    <th>{{ __('Objekt / Anlage') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Inhaber') }}</th>
                    <th>{{ __('Sperr-Aufgabe') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($media as $medium)
                <tr class="hover">
                    <td>
                        <a class="link link-hover font-medium" href="{{ route('access-media.show', $medium) }}">
                            {{ $medium->label ?: __('Medium') }} <span class="font-mono text-xs text-muted">…{{ $medium->number_suffix }}</span>
                        </a>
                    </td>
                    <td class="text-sm">{{ $medium->type->label() }}</td>
                    <td class="text-sm text-base-content/70">
                        {{ $medium->site?->name ?? '—' }}
                        @if ($medium->system_name)<span class="block text-xs">{{ $medium->system_name }}</span>@endif
                    </td>
                    <td><x-status-badge :tone="$medium->status->tone()" size="sm">{{ $medium->status->label() }}</x-status-badge></td>
                    <td class="text-sm">{{ $medium->holderDisplay() ?? '—' }}</td>
                    <td class="text-sm">
                        @if ($medium->blockTask)
                            @php($overdue = $medium->blockTask->status?->value !== 'done' && $medium->blockTask->due_date?->isPast())
                            <span class="@if ($overdue) text-error font-medium @else text-base-content/70 @endif">
                                {{ $medium->blockTask->status?->value === 'done' ? __('erledigt') : __('offen (fällig :due)', ['due' => $medium->blockTask->due_date?->format('d.m.Y') ?? '—']) }}
                            </span>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$media" standing />
    @endif
</x-index-page>
@endsection
