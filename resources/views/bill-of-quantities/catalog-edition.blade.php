{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : catalog-edition.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Katalogausgabe wechseln: :name', ['name' => $bill->name]))
@section('nav-title', __('Katalogausgabe wechseln'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <div class="text-sm text-base-content/70">
                {{ $bill->name }}
                @if ($from) · {{ $from->name }} {{ $from->edition }} @endif
            </div>
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('bill-of-quantities.catalog-assignment', $bill)" show-label>{{ __('Zur Zuordnung') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($catalog === null || $from === null)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">swap_horiz</span>'
                       :title="__('Kein Kostengruppenkatalog mit hinterlegtem Stamm.')"
                       :message="__('Ein Ausgabenwechsel setzt voraus, dass die Ausgangsausgabe bekannt ist — sonst wäre unklar, was „310“ heute bedeutet.')" />
    @else
        <x-card :title="__('Zielausgabe wählen')">
            <form method="GET" action="{{ route('bill-of-quantities.catalog-edition', $bill) }}" class="flex flex-wrap items-end gap-2">
                <label class="fieldset">
                    <span class="label-text text-xs">{{ __('Neue Ausgabe') }}</span>
                    <select name="to" class="select select-sm select-bordered w-72" data-autosubmit>
                        <option value="">{{ __('— bitte wählen —') }}</option>
                        @foreach ($targets as $target)
                            <option value="{{ $target->sqid }}" @selected($to?->id === $target->id)>{{ $target->name }} {{ $target->edition }}</option>
                        @endforeach
                    </select>
                </label>
                <x-icon-btn icon="preview" size="sm" type="submit" show-label>{{ __('Vorschau') }}</x-icon-btn>
            </form>
            <p class="mt-2 text-sm text-base-content/70">
                {{ __('Ein Wechsel der Norm ist eine fachliche Entscheidung, keine Umnummerierung. Zuordnungen ohne eindeutige Entsprechung bleiben unverändert stehen.') }}
            </p>
        </x-card>

        @if ($preview !== null)
            <x-card :title="__('Vorschau')">
                <p class="mb-3 text-sm">
                    {{ __(':mapped mit Entsprechung, :unmapped ohne.', ['mapped' => $preview['mapped'], 'unmapped' => $preview['unmapped']]) }}
                </p>

                @if (empty($preview['rows']))
                    <x-empty-state icon="swap_horiz" compact :title="__('Keine Zuordnungen im Leistungsverzeichnis.')" />
                @else
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <th>{{ __('Bisher') }}</th>
                                <th>{{ __('Neu') }}</th>
                                <th>{{ __('Hinweis') }}</th>
                            </tr>
                        </x-slot:head>
                        @foreach ($preview['rows'] as $row)
                            <tr>
                                <td class="font-mono text-xs">{{ $row['from'] }}</td>
                                <td class="font-mono text-xs">
                                    @if ($row['to'] === null)
                                        <span class="text-warning">{{ __('keine Entsprechung') }}</span>
                                    @else
                                        {{ $row['to'] }} <span class="font-sans text-base-content/70">{{ $row['label'] }}</span>
                                    @endif
                                </td>
                                <td class="text-xs text-base-content/70">{{ $row['note'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </x-table>
                @endif

                @if ($canManage && $to !== null && $preview['mapped'] > 0)
                    <x-action-form class="mt-3" :action="route('bill-of-quantities.catalog-edition', [$bill, 'to' => $to->sqid, 'confirm' => 1])"
                                   :confirm="__('Ausgabe wechseln? Zuordnungen ohne Entsprechung bleiben stehen.')"
                                   :confirm-label="__('Wechseln')">
                        <x-icon-btn icon="swap_horiz" tone="primary" size="sm" type="submit" show-label>{{ __('Ausgabe wechseln') }}</x-icon-btn>
                    </x-action-form>
                @endif
            </x-card>
        @endif
    @endif
</x-page-shell>
@endsection
