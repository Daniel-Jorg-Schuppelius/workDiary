{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Zuordnungsregeln') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Zuordnungsregeln'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    $matchLabels = [
        'work_category' => __('Leistungsbereich'),
        'keyword' => __('Stichwort'),
    ];
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('Welche Leistung auf welche Kostengruppe schlägt — als Vorschlag, nicht als Automatik.')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger
                        :href="route('catalog-rules.create')" show-label>{{ __('Regel anlegen') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    @if ($rules->isEmpty())
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">auto_fix_high</span>'
                       :title="__('Noch keine Regel angelegt.')"
                       :message="__('Eine Regel hält fest, welche Leistung üblicherweise auf welche Kostengruppe schlägt. Angewandt wird sie nur auf Positionen ohne Zuordnung.')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th class="text-right">{{ __('Rang') }}</th>
                    <th>{{ __('Trifft auf') }}</th>
                    <th>{{ __('Wert') }}</th>
                    <th>{{ __('Katalog') }}</th>
                    <th>{{ __('Kostengruppe') }}</th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @foreach ($rules as $rule)
                <tr class="hover @unless ($rule->active) opacity-60 @endunless">
                    <td class="text-right tabular-nums">{{ $rule->priority }}</td>
                    <td>
                        {{ $matchLabels[$rule->match_type] ?? $rule->match_type }}
                        @unless ($rule->active)<span class="wd-badge badge-outline">{{ __('Inaktiv') }}</span>@endunless
                    </td>
                    <td class="font-mono text-xs">{{ $rule->match_value }}</td>
                    <td class="text-base-content/70">{{ $rule->registry?->name }} {{ $rule->registry?->edition }}</td>
                    <td class="font-mono text-xs">{{ $rule->code }}</td>
                    <td class="text-right">
                        @if ($canManage)
                            <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger
                                        :href="route('catalog-rules.edit', $rule)" />
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-index-page>
@endsection
