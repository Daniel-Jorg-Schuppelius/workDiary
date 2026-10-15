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
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Aus Ihren Standortdaten abgeleitete Zeitvorschläge prüfen und buchen.')">
    @include('location._review_tabs')

    @if ($entries->isEmpty())
        <x-empty-state framed
            icon="where_to_vote" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Datum') }}</th>
                    <th>{{ __('Kunde') }}</th>
                    <th>{{ __('Projekt') }}</th>
                    <th>{{ __('Auftrag') }}</th>
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
                    @php($dayOrders = $ordersByDate->get($entry->suggested_date->toDateString(), collect()))
                    <td>
                        @if ($dayOrders->isEmpty())
                            {{ $entry->diaryEntry?->title ?? '—' }}
                        @else
                            {{-- Auftrag am Vorschlag (MVP-1182): Treffer vorbelegt, Wechsel oder „ohne Auftrag“ möglich; wirkt beim Buchen. --}}
                            <select name="diary_entry_id" form="confirm-{{ $entry->sqid }}" class="select select-sm select-bordered w-48" aria-label="{{ __('Auftrag') }}">
                                <option value="">{{ __('ohne Auftrag') }}</option>
                                @foreach ($dayOrders as $order)
                                    <option value="{{ $order->sqid }}" @selected((int) $order->id === (int) $entry->diary_entry_id)>{{ $order->title }}</option>
                                @endforeach
                            </select>
                        @endif
                    </td>
                    <td>{{ $entry->started_at->orgTz()->format('H:i') }}–{{ $entry->ended_at->orgTz()->format('H:i') }}</td>
                    <td class="text-end">{{ \App\Support\Formats::duration((int) $entry->minutes, 'clock') }}</td>
                    <td>{{ $entry->description }}</td>
                    <td class="text-right whitespace-nowrap">
                        <x-action-form :action="route('location.review.confirm', $entry)" id="confirm-{{ $entry->sqid }}">
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
        <x-pagination :paginator="$entries" standing />
    @endif
</x-index-page>
@endsection
