{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : catalog-assignment.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Kostengruppen zuordnen: :name', ['name' => $bill->name]))
@section('nav-title', __('Kostengruppen zuordnen'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $items */
    /** @var array<string, string> $options */
    $codeOf = static function ($item) use ($catalog): ?object {
        return $catalog === null ? null : $item->catalogAssignments->firstWhere('catalog_key', $catalog->catalog_key);
    };
    $sourceLabels = [
        'import' => __('aus der Datei'),
        'manual' => __('von Hand'),
        'rule' => __('Vorschlag'),
    ];
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('Kostengruppe je Position setzen — einzeln oder für eine Auswahl.')">
    <x-slot:actions>
        <x-icon-btn icon="category" size="sm" :href="route('bill-of-quantities.cost-groups', $bill)" show-label>{{ __('Auswertung') }}</x-icon-btn>
        <x-icon-btn icon="swap_horiz" size="sm" :href="route('bill-of-quantities.catalog-edition', $bill)" show-label>{{ __('Ausgabe wechseln') }}</x-icon-btn>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('bill-of-quantities.show', $bill)" show-label>{{ __('Zum Leistungsverzeichnis') }}</x-icon-btn>
    </x-slot:actions>

    @if ($catalog === null)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">category</span>'
                       :title="__('Kein Kostengruppenkatalog im Leistungsverzeichnis.')"
                       :message="__('Kostengruppen kommen mit der Datei der Vergabestelle. Ohne Katalog im Kopf gibt es nichts zuzuordnen — die Positionen erscheinen dann vollständig unter „ohne Zuordnung“.')" />
    @else
        <x-filter-bar :action="route('bill-of-quantities.catalog-assignment', $bill)"
                      :reset="route('bill-of-quantities.catalog-assignment', $bill)">
            <label class="label cursor-pointer gap-2 shrink-0">
                <input type="checkbox" name="unassigned" value="1" class="checkbox checkbox-sm" @checked($onlyUnassigned)>
                <span class="label-text">{{ __('Nur ohne Kostengruppe') }}</span>
            </label>
            @if ($code !== '')
                {{-- Aus der Auswertung hierher gesprungen: Der Filter bleibt
                     sichtbar, sonst wundert man sich über die kurze Liste. --}}
                <x-filter-field :label="__('Kostengruppe')" for="kg-code" class="shrink-0">
                    <input id="kg-code" name="code" value="{{ $code }}" maxlength="40"
                           class="input input-sm input-bordered w-32 font-mono">
                </x-filter-field>
            @endif
        </x-filter-bar>

        @if (empty($options))
            <div class="alert alert-warning">
                <span class="material-symbols-outlined" aria-hidden="true">warning</span>
                <span>{{ __('Zum Katalog „:type“ ist kein Stamm hinterlegt — die Nummern lassen sich nur frei eintragen.', ['type' => $catalog->type ?? $catalog->catalog_key]) }}</span>
            </div>
        @endif

        {{-- flex-1: das Formular muss die Resthöhe füllen, sonst kollabiert
             die scroll=flex-Tabelle darin auf ihre Mindesthöhe (I10). --}}
        <form method="POST" action="{{ route('bill-of-quantities.catalog-assignment.bulk', $bill) }}"
              class="flex min-h-0 flex-1 flex-col gap-2">
            @csrf

            @if ($canManage)
                <div class="flex flex-wrap items-end gap-2">
                    <label class="fieldset">
                        <span class="label-text text-xs">{{ __('Kostengruppe für die Auswahl') }}</span>
                        @if (empty($options))
                            <input name="code" maxlength="40" class="input input-sm input-bordered w-56" placeholder="{{ __('z. B. 310') }}">
                        @else
                            <select name="code" class="select select-sm select-bordered w-72">
                                <option value="">{{ __('— Zuordnung entfernen —') }}</option>
                                @foreach ($options as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                    </label>
                    <x-icon-btn icon="playlist_add_check" tone="primary" size="sm" type="submit" show-label>{{ __('Auswahl zuordnen') }}</x-icon-btn>
                    {{-- Der Regellauf füllt nur Lücken — vorhandene Zuordnungen bleiben. --}}
                    <x-icon-btn icon="auto_fix_high" size="sm" show-label form="apply-catalog-rules" type="submit"
                                :title="__('Vorschlagsregeln anwenden — vorhandene Zuordnungen bleiben unverändert')">{{ __('Regeln anwenden') }}</x-icon-btn>
                </div>
            @endif

            <x-table scroll="flex" :pinRows="true">
                <x-slot:head>
                    <tr>
                        @if ($canManage)<th class="w-8"></th>@endif
                        <th>{{ __('gaeb.columns.reference_no') }}</th>
                        <th>{{ __('gaeb.columns.short_text') }}</th>
                        <th class="text-right">{{ __('gaeb.columns.quantity') }}</th>
                        <th>{{ __('Kostengruppe') }}</th>
                        <th>{{ __('Herkunft') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($items as $item)
                    @php $assignment = $codeOf($item); @endphp
                    <tr class="hover">
                        @if ($canManage)
                            <td>
                                <input type="checkbox" name="items[]" value="{{ $item->sqid }}" class="checkbox checkbox-sm"
                                       aria-label="{{ __('Position :ref auswählen', ['ref' => $item->reference_no]) }}">
                            </td>
                        @endif
                        <td class="whitespace-nowrap font-mono text-sm">{{ $item->reference_no }}</td>
                        <td>
                            {{ $item->short_text ?: '—' }}
                            @if ($item->section)
                                <div class="text-xs text-base-content/60">{{ $item->section->label }}</div>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">
                            {{ $item->quantity !== null
                                ? rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($item->quantity?->getValue()->toFloat() ?? 0.0), 3, withThousandsSeparator: true), '0'), ',')
                                : '—' }}
                            {{ $item->unit }}
                        </td>
                        <td>
                            @if ($canManage)
                                {{-- Eigenes Formular je Zeile: Die Zuordnung einer Position
                                     darf nicht an der Massenauswahl hängen. --}}
                                @if (empty($options))
                                    <input form="assign-{{ $item->sqid }}" name="code" maxlength="40"
                                           value="{{ $assignment?->code }}" class="input input-xs input-bordered w-28">
                                @else
                                    <select form="assign-{{ $item->sqid }}" name="code" class="select select-xs select-bordered w-64" data-autosubmit>
                                        <option value="">{{ __('— ohne —') }}</option>
                                        @foreach ($options as $code => $label)
                                            <option value="{{ $code }}" @selected($assignment?->code === $code)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            @else
                                {{ $assignment?->code ?? '—' }}
                            @endif
                        </td>
                        <td class="text-xs text-base-content/70">
                            {{ $assignment === null ? '—' : ($sourceLabels[$assignment->source] ?? $assignment->source) }}
                        </td>
                    </tr>

                    {{-- Teilmengen (MVP-639): Sie schlagen in der Auswertung die
                         Zuordnung der Position — deshalb bekommen sie eine eigene
                         Zeile und ein eigenes Feld. --}}
                    @foreach ($item->quantitySplits as $split)
                        @php $splitAssignment = $catalog === null ? null : $split->catalogAssignments->firstWhere('catalog_key', $catalog->catalog_key); @endphp
                        <tr class="bg-base-200/40">
                            @if ($canManage)<td></td>@endif
                            <td></td>
                            <td class="pl-6 text-sm text-base-content/70">
                                {{ __('Teilmenge') }} {{ $loop->iteration }}
                            </td>
                            <td class="text-right tabular-nums text-base-content/70">
                                @if ($split->percent !== null)
                                    {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $split->percent, 2) }} %
                                @elseif ($split->quantity !== null)
                                    {{ rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $split->quantity, 3, withThousandsSeparator: true), '0'), ',') }} {{ $item->unit }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($canManage && ! empty($options))
                                    <select form="assign-split-{{ $split->sqid }}" name="code" class="select select-xs select-bordered w-64" data-autosubmit>
                                        <option value="">{{ __('— ohne —') }}</option>
                                        @foreach ($options as $code => $label)
                                            <option value="{{ $code }}" @selected($splitAssignment?->code === $code)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($canManage)
                                    <input form="assign-split-{{ $split->sqid }}" name="code" maxlength="40"
                                           value="{{ $splitAssignment?->code }}" class="input input-xs input-bordered w-28">
                                @else
                                    {{ $splitAssignment?->code ?? '—' }}
                                @endif
                            </td>
                            <td class="text-xs text-base-content/70">
                                {{ $splitAssignment === null ? '—' : ($sourceLabels[$splitAssignment->source] ?? $splitAssignment->source) }}
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </x-table>
        </form>

        {{-- Die Zeilenformulare stehen außerhalb der Massenauswahl: ein
             verschachteltes <form> wäre ungültiges HTML. --}}
        @if ($canManage)
            <form id="apply-catalog-rules" method="POST"
                  action="{{ route('bill-of-quantities.catalog-rules.apply', $bill) }}" class="hidden">@csrf</form>
            @foreach ($items as $item)
                <form id="assign-{{ $item->sqid }}" method="POST"
                      action="{{ route('bill-of-quantities.items.catalog-assignment', $item) }}" class="hidden">@csrf</form>
                @foreach ($item->quantitySplits as $split)
                    <form id="assign-split-{{ $split->sqid }}" method="POST"
                          action="{{ route('bill-of-quantities.splits.catalog-assignment', $split) }}" class="hidden">@csrf</form>
                @endforeach
            @endforeach
        @endif

        <x-pagination :paginator="$items" standing />
    @endif
</x-index-page>
@endsection
