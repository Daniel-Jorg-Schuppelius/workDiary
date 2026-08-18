{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Baukostenkatalog: :name', ['name' => $catalog->name]))
@section('nav-title', $catalog->name)
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    $money = static fn (?string $value): string => $value === null
        ? '—'
        : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $value, 2, withThousandsSeparator: true) . ' €';
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('Kennwerte je Kostenelement — von, Mittel und bis.')">
    <x-slot:actions>
        <x-icon-btn icon="download" size="sm" :href="route('cost-catalogs.export', $catalog)" show-label>{{ __('GAEB X50') }}</x-icon-btn>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('cost-catalogs.index')" show-label>{{ __('Alle Kataloge') }}</x-icon-btn>
    </x-slot:actions>

    @if ($elements->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">price_change</span>'
                       :title="__('Der Katalog enthält keine Elemente.')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Nummer') }}</th>
                    <th>{{ __('Bezeichnung') }}</th>
                    <th>{{ __('Einheit') }}</th>
                    <th class="text-right">{{ __('von') }}</th>
                    <th class="text-right">{{ __('Mittel') }}</th>
                    <th class="text-right">{{ __('bis') }}</th>
                    <th>{{ __('Artikel') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($elements as $element)
                <tr class="hover">
                    <td class="whitespace-nowrap font-mono text-sm">{{ $element->code ?? '—' }}</td>
                    <td style="padding-left: {{ ($element->level - 1) * 1.25 }}rem">{{ $element->label }}</td>
                    <td class="text-base-content/70">{{ $element->unit ?? '—' }}</td>
                    <td class="text-right tabular-nums text-base-content/70">{{ $money($element->unit_price_from) }}</td>
                    {{-- Der Mittelwert ist der Rechenwert; die Spanne daneben sagt,
                         wie sicher er ist. --}}
                    <td class="text-right tabular-nums font-medium">{{ $money($element->unit_price_avg) }}</td>
                    <td class="text-right tabular-nums text-base-content/70">{{ $money($element->unit_price_to) }}</td>
                    <td>
                        {{-- Die Verknüpfung ersetzt keinen Preis: Der Kennwert bleibt
                             ein Anhaltspunkt aus fremder Quelle. --}}
                        @if ($canManage)
                            <select form="link-{{ $element->id }}" name="article" class="select select-xs select-bordered w-56" data-autosubmit>
                                <option value="">{{ __('— ohne —') }}</option>
                                @foreach ($articles as $article)
                                    <option value="{{ $article->sqid }}" @selected($element->article_id === $article->id)>
                                        {{ $article->number ? $article->number . ' · ' : '' }}{{ $article->name }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            {{ $element->article?->name ?? '—' }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$elements" standing />

        {{-- Zeilenformulare außerhalb der Tabelle: verschachtelte <form> sind
             ungültiges HTML. --}}
        @if ($canManage)
            @foreach ($elements as $element)
                <form id="link-{{ $element->id }}" method="POST"
                      action="{{ route('cost-catalogs.link-article', [$catalog, $element]) }}" class="hidden">@csrf</form>
            @endforeach
        @endif
    @endif
</x-index-page>
@endsection
