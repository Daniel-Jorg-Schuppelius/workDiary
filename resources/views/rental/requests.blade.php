{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : requests.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Portal-Verleihanfragen entscheiden (MVP-714) — erwartet: $requests, $status, $canManage, $assetsByGroup --}}
@extends('layouts.app')

@section('title', __('Verleih-Anfragen'))
@section('nav-title', __('Verleih-Anfragen'))
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page :subtitle="__('Portal-Anfragen entscheiden — erst die Annahme legt Verleihakte (Entwurf) und Vormerkung an.')">
    <x-slot:actions>
        <x-icon-btn icon="forklift" size="sm" :href="route('rental.index')" show-label>{{ __('Verleihakten') }}</x-icon-btn>
        <x-icon-btn icon="calendar_month" size="sm" :href="route('rental.calendar')" show-label>{{ __('Kalender') }}</x-icon-btn>
    </x-slot:actions>

    <x-filter-bar :action="route('rental.requests.index')" :reset="route('rental.requests.index')">
        <select name="status" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Status') }}">
            <option value="open" @selected($status === 'open')>{{ __('Offene Anfragen') }}</option>
            <option value="all" @selected($status === 'all')>{{ __('Alle') }}</option>
            @foreach (\App\Enums\Rental\RentalRequestStatus::cases() as $case)
                <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    <x-table scroll="flex" table-sort="client" :caption="__('Verleih-Anfragen')">
        <x-slot:head>
            <tr>
                <x-table.th sort type="date" default="asc">{{ __('Zeitraum') }}</x-table.th>
                <x-table.th sort>{{ __('Kunde') }}</x-table.th>
                <x-table.th sort>{{ __('Gerät / Gruppe') }}</x-table.th>
                <x-table.th>{{ __('Notiz') }}</x-table.th>
                <x-table.th sort>{{ __('Status') }}</x-table.th>
                <x-table.th align="right">{{ __('Aktionen') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($requests as $request)
            <tr class="hover">
                <td class="whitespace-nowrap" data-sort-value="{{ $request->starts_at->toIso8601String() }}">
                    {{ $request->starts_at->fdatetime() }} – {{ $request->ends_at->fdatetime() }}
                    <span class="block text-xs text-muted">{{ __('eingegangen :date', ['date' => $request->created_at?->fdatetime()]) }}</span>
                </td>
                <td>
                    {{ $request->customer?->name ?? '—' }}
                    <span class="block text-xs text-muted">{{ $request->portalUser?->name }}</span>
                </td>
                <td>{{ $request->subjectLabel() }}</td>
                <td class="max-w-xs whitespace-pre-line text-sm">{{ $request->note ?? '—' }}</td>
                <td>
                    <x-status-badge size="md" outline :tone="$request->status->badgeTone()">{{ $request->status->label() }}</x-status-badge>
                    @if ($request->rentalCase !== null)
                        <a class="link link-hover block text-xs" href="{{ route('rental.show', $request->rentalCase) }}">{{ $request->rentalCase->number }}</a>
                    @elseif ($request->decline_reason)
                        <span class="block text-xs text-muted">{{ $request->decline_reason }}</span>
                    @endif
                    @if ($request->decidedBy !== null)
                        <span class="block text-xs text-muted">{{ $request->decidedBy->name }} · {{ $request->decided_at?->fdatetime() }}</span>
                    @endif
                </td>
                <td class="text-right">
                    @if ($canManage && $request->isOpen())
                        <div class="flex flex-col items-end gap-1">
                            <form method="POST" action="{{ route('rental.requests.accept', $request) }}" class="flex items-center justify-end gap-1">
                                @csrf
                                @if ($request->asset_id === null)
                                    {{-- Gruppenanfrage: Gerät wählt die Leitung. --}}
                                    <select name="asset" class="select select-xs select-bordered w-40" aria-label="{{ __('Gerät') }}" required>
                                        <option value="">{{ __('Gerät wählen') }}</option>
                                        @foreach ($assetsByGroup->get($request->group_code, collect()) as $asset)
                                            <option value="{{ $asset->sqid }}">{{ $asset->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                <x-icon-btn icon="check" tone="primary" size="sm" type="submit" show-label>{{ __('Annehmen') }}</x-icon-btn>
                            </form>
                            <form method="POST" action="{{ route('rental.requests.decline', $request) }}" class="flex items-center justify-end gap-1">
                                @csrf
                                <input type="text" name="reason" required minlength="3" maxlength="500"
                                       class="input input-xs input-bordered w-40"
                                       aria-label="{{ __('Ablehnungsgrund') }}"
                                       placeholder="{{ __('Ablehnungsgrund (geht an den Kunden)') }}">
                                <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('Ablehnen') }}</button>
                            </form>
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="6" icon="mark_email_unread"
                           :title="__('Keine Verleih-Anfragen.')" />
        @endforelse
    </x-table>

    <x-pagination :paginator="$requests" standing />
</x-index-page>
@endsection
