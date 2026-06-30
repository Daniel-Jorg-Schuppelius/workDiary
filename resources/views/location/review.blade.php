{{--
  Created on   : Fri Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : review.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Standort-Vorschläge'))
@section('nav-title', __('Standort-Vorschläge'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Aus deinen Standortdaten abgeleitete Zeitvorschläge prüfen und buchen.')">
    @if ($entries->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">where_to_vote</span>' />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Datum') }}</th>
                    <th>{{ __('Kunde') }}</th>
                    <th>{{ __('Projekt') }}</th>
                    <th>{{ __('Von–Bis') }}</th>
                    <th class="text-end">{{ __('Dauer') }}</th>
                    <th>{{ __('Bezeichnung') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($entries as $entry)
                <tr>
                    <td>{{ $entry->suggested_date->translatedFormat('d.m.Y') }}</td>
                    <td>{{ $entry->customer?->name ?? '—' }}</td>
                    <td>{{ $entry->project?->name ?? '—' }}</td>
                    <td>{{ $entry->started_at->format('H:i') }}–{{ $entry->ended_at->format('H:i') }}</td>
                    <td class="text-end">{{ intdiv($entry->minutes, 60) }}:{{ str_pad((string) ($entry->minutes % 60), 2, '0', STR_PAD_LEFT) }} h</td>
                    <td>{{ $entry->description }}</td>
                    <td class="text-right whitespace-nowrap">
                        <x-action-form :action="route('location.review.confirm', $entry)">
                            <x-icon-btn icon="check" tone="success" size="sm" type="submit" :label="__('Buchen')" />
                        </x-action-form>
                        <x-action-form :action="route('location.review.dismiss', $entry)"
                                       :confirm="__('Vorschlag verwerfen?')" :confirm-label="__('Verwerfen')">
                            <x-icon-btn icon="close" tone="ghost" size="sm" type="submit" :label="__('Verwerfen')" />
                        </x-action-form>
                    </td>
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$entries" />
    @endif
</x-index-page>
@endsection
