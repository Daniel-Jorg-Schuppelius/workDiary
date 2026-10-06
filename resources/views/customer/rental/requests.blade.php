{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : requests.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Verleih-Anfrage im Portal (Feature 073, MVP-714): freigegebenes Sortiment, grobe Verfügbarkeit, zweiphasige Anfrage.
     Erwartet: $profiles, $groups, $from, $to, $availability, $prices, $direct, $requests --}}
@extends('customer.layout')

@section('title', __('Verleih-Anfrage'))

@section('content')
    <h1 class="mb-1 text-2xl font-semibold">{{ __('Verleih-Anfrage') }}</h1>
    <p class="mb-4 text-sm text-base-content/70">{{ $direct ? __('rental.portal.direct_intro') : __('Sie fragen Gerät oder Gerätegruppe für einen Zeitraum an — wir prüfen die Verfügbarkeit und bestätigen verbindlich.') }}</p>

    <div class="mb-6 rounded-box bg-base-100 p-4 shadow">
        <form method="GET" action="{{ route('customer.rentals.requests.index') }}" class="flex flex-wrap items-end gap-3">
            <x-date-range type="datetime-local" from-name="from" to-name="to"
                          :from="$from?->format('Y-m-d\TH:i')" :to="$to?->format('Y-m-d\TH:i')"
                          :label="__('Gewünschter Zeitraum')" required />
            <x-button type="submit">{{ __('Verfügbarkeit prüfen') }}</x-button>
        </form>

        @if ($profiles->isEmpty())
            <p class="mt-4 text-sm text-muted">{{ __('Derzeit sind keine Geräte für Anfragen freigegeben.') }}</p>
        @else
            <x-table class="mt-4" :caption="__('Anfragbare Geräte')">
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('Gerät') }}</x-table.th>
                        <x-table.th>{{ __('Gerätegruppe') }}</x-table.th>
                        <x-table.th>{{ __('Verfügbarkeit') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('rental.portal.price') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @foreach ($profiles as $profile)
                    <tr>
                        <td>{{ $profile->asset?->name }}</td>
                        <td>{{ $profile->group_code ?? '—' }}</td>
                        <td>
                            @if (array_key_exists((int) $profile->asset_id, $availability))
                                @if ($availability[(int) $profile->asset_id])
                                    <x-status-badge tone="success">{{ __('frei') }}</x-status-badge>
                                @else
                                    <x-status-badge tone="warning">{{ __('belegt') }}</x-status-badge>
                                @endif
                            @else
                                <span class="text-muted">{{ __('Zeitraum wählen') }}</span>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @if (($prices[(int) $profile->asset_id] ?? null) !== null)
                                {{ __('rental.portal.price_estimate', ['amount' => $prices[(int) $profile->asset_id]->format()]) }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>

            @if ($from !== null && $to !== null)
                <form method="POST" action="{{ route('customer.rentals.requests.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                    @csrf
                    <input type="hidden" name="from" value="{{ $from->format('Y-m-d H:i') }}">
                    <input type="hidden" name="to" value="{{ $to->format('Y-m-d H:i') }}">
                    <x-select-field name="subject" :label="__('Gerät oder Gerätegruppe')" required>
                        <option value="">{{ __('— bitte wählen —') }}</option>
                        <optgroup label="{{ __('Geräte') }}">
                            @foreach ($profiles as $profile)
                                <option value="asset:{{ $profile->asset?->sqid }}" @selected(old('subject') === 'asset:' . $profile->asset?->sqid)>{{ $profile->asset?->name }}</option>
                            @endforeach
                        </optgroup>
                        @if ($groups->isNotEmpty())
                            <optgroup label="{{ __('Gerätegruppen (irgendein Gerät)') }}">
                                @foreach ($groups as $group)
                                    <option value="group:{{ $group }}" @selected(old('subject') === 'group:' . $group)>{{ $group }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    </x-select-field>
                    <x-textarea-field name="note" :label="__('Zweck / Notiz (optional)')" rows="2">{{ old('note') }}</x-textarea-field>
                    <div class="sm:col-span-2 flex justify-end gap-2">
                        @if ($direct)
                            {{-- Direktbuchung (MVP-916): nur ein bestimmtes, freies Gerät; der Dienst prüft unter Sperre. --}}
                            <x-button type="submit" tone="secondary" name="booking" value="direct" icon="event_available" :title="__('rental.portal.direct_hint')"><span>{{ __('rental.portal.direct_book') }}</span></x-button>
                        @endif
                        <x-button type="submit" tone="primary" icon="send"><span>{{ __('Anfrage senden') }}</span></x-button>
                    </div>
                </form>
            @endif
        @endif
    </div>

    <h2 class="mb-2 font-semibold">{{ __('Meine Anfragen') }}</h2>
    <x-table>
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('Zeitraum') }}</x-table.th>
                <x-table.th>{{ __('Gerät') }}</x-table.th>
                <x-table.th>{{ __('Status') }}</x-table.th>
                <x-table.th></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($requests as $request)
            <tr>
                <td class="whitespace-nowrap">{{ $request->starts_at->fdatetime() }} – {{ $request->ends_at->fdatetime() }}</td>
                <td>{{ $request->subjectLabel() }}</td>
                <td>
                    <x-status-badge :tone="$request->status->badgeTone()">{{ $request->is_direct ? __('rental.portal.direct_status') : $request->status->label() }}</x-status-badge>
                    @if ($request->status === \App\Enums\Rental\RentalRequestStatus::Declined && $request->decline_reason)
                        <span class="block text-xs text-muted">{{ $request->decline_reason }}</span>
                    @endif
                </td>
                <td class="text-right">
                    @if ($request->isOpen())
                        <form method="POST" action="{{ route('customer.rentals.requests.withdraw', $request) }}" class="flex justify-end">
                            @csrf
                            <x-button type="submit" tone="ghost" size="xs">{{ __('Zurücknehmen') }}</x-button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="4" :title="__('Noch keine Verleih-Anfragen.')" />
        @endforelse
    </x-table>
@endsection
