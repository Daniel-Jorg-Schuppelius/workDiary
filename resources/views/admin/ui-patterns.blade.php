{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : ui-patterns.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- UI-Grundbausteine (MVP-958): lebende Vorschau statt Storybook. Erwartet: $tones --}}
@extends('layouts.app')

@section('title', __('ui_patterns.title'))
@section('nav-title', __('ui_patterns.title'))

@section('content')
<x-index-page :subtitle="__('ui_patterns.subtitle')">
    <x-card :title="__('ui_patterns.section.buttons')">
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($tones as $tone)
                <x-icon-btn icon="star" :tone="$tone" size="sm" show-label :label="$tone" />
            @endforeach
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            @foreach (['xs', 'sm', 'md', 'lg'] as $size)
                <x-icon-btn icon="edit" :size="$size" :label="$size" />
            @endforeach
            <x-button>btn-primary</x-button>
            <x-button tone="plain">btn</x-button>
        </div>
        <p class="mt-2 text-xs text-muted"><code>&lt;x-icon-btn icon tone size show-label href type&gt;</code></p>
    </x-card>

    {{-- Seitenkopf mit Überlaufmenü (MVP-966/967). --}}
    <x-card :title="__('ui_patterns.section.toolbar')" class="mt-4">
        <x-page-toolbar :title="__('ui_patterns.sample.name')" :subtitle="__('ui_patterns.sample.hint')" :back="route('admin.ui-patterns.index')">
            <x-slot:badges>
                <x-status-badge tone="warning" size="sm" outline>warning</x-status-badge>
            </x-slot:badges>
            <x-slot:actions>
                <x-icon-btn icon="picture_as_pdf" size="sm" show-label>PDF</x-icon-btn>
                <x-action-menu icon="download" :label="__('Export')">
                    <x-icon-btn icon="download" size="sm" show-label>CSV</x-icon-btn>
                    <x-icon-btn icon="table_view" size="sm" show-label>Excel</x-icon-btn>
                </x-action-menu>
                <x-icon-btn icon="mail" size="sm" show-label>{{ __('Per E-Mail senden') }}</x-icon-btn>
                <x-icon-btn icon="tune" size="sm" placement="menu" show-label>{{ __('ui_patterns.sample.rare_action') }}</x-icon-btn>
                <x-icon-btn icon="send" tone="primary" size="sm" placement="bar" show-label>{{ __('ui_patterns.sample.main_action') }}</x-icon-btn>
                <x-icon-btn icon="delete" tone="error" size="sm" placement="danger" show-label>{{ __('Löschen') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
        <p class="mt-2 text-xs text-muted">{{ __('ui_patterns.sample.toolbar_hint') }}</p>
        <p class="mt-1 text-xs text-muted"><code>&lt;x-page-toolbar back-route badges actions&gt;</code> · <code>placement="bar|menu|danger"</code> · <code>&lt;x-action-menu icon label icon-only&gt;</code></p>
    </x-card>

    <x-card :title="__('ui_patterns.section.badges')" class="mt-4">
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($tones as $tone)
                <x-status-badge :tone="$tone">{{ $tone }}</x-status-badge>
                <x-status-badge :tone="$tone" outline>{{ $tone }}</x-status-badge>
                <span class="wd-badge badge-{{ $tone }}">wd-badge</span>
            @endforeach
        </div>
    </x-card>

    <x-card :title="__('ui_patterns.section.kpi')" class="mt-4">
        <div class="grid gap-3 sm:grid-cols-4">
            <x-kpi-tile :label="__('ui_patterns.sample.kpi')" :value="1280" />
            <x-kpi-tile :label="__('ui_patterns.sample.kpi')" :value="42" tone="success" :hint="__('ui_patterns.sample.hint')" />
            <x-kpi-tile :label="__('ui_patterns.sample.kpi')" :value="7" tone="warning" />
            <x-kpi-tile :label="__('ui_patterns.sample.kpi')" :value="-3" tone="error" />
        </div>
    </x-card>

    <x-card :title="__('ui_patterns.section.table')" class="mt-4" padding="p-0">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm" show-label :label="__('ui_patterns.sample.action')" />
        </x-slot:actions>
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('ui_patterns.sample.name') }}</th>
                    <th class="text-right">{{ __('ui_patterns.sample.amount') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </x-slot:head>
            @foreach ([['Alpha', '1.200,00 €', 'success'], ['Beta', '84,50 €', 'warning']] as [$name, $amount, $tone])
                <tr>
                    <td>{{ $name }}</td>
                    <td class="text-right tabular-nums">{{ $amount }}</td>
                    <td><x-status-badge :tone="$tone">{{ $tone }}</x-status-badge></td>
                    <td class="text-right"><x-icon-btn icon="visibility" :label="__('Anzeigen')" /></td>
                </tr>
            @endforeach
        </x-table>
        <x-table bare>
            <x-table.empty icon="inbox" :colspan="4" :title="__('ui_patterns.sample.empty')" compact />
        </x-table>
    </x-card>

    <x-card :title="__('ui_patterns.section.form')" class="mt-4">
        <x-form-group :legend="__('ui_patterns.sample.group')" icon="edit_note" tone="primary" cols="2">
            <x-input-field name="pattern_text" :label="__('ui_patterns.sample.text')" :hint="__('ui_patterns.sample.hint')" />
            <x-select-field name="pattern_select" :label="__('ui_patterns.sample.select')">
                <option>A</option>
                <option>B</option>
            </x-select-field>
            <x-input-field name="pattern_date" type="date" :label="__('ui_patterns.sample.date')" />
            <x-input-field name="pattern_number" type="number" step="0.01" :label="__('ui_patterns.sample.amount')" />
            <x-textarea-field name="pattern_note" rows="2" span="2" :label="__('ui_patterns.sample.note')"></x-textarea-field>
        </x-form-group>
    </x-card>

    <x-card :title="__('ui_patterns.section.details')" class="mt-4">
        <x-detail-grid class="grid-cols-2">
            <x-detail-grid.row :label="__('ui_patterns.sample.name')">Alpha</x-detail-grid.row>
            <x-detail-grid.row :label="__('ui_patterns.sample.amount')">1.200,00 €</x-detail-grid.row>
        </x-detail-grid>
        <div role="status" class="alert alert-info mt-3 text-sm">{{ __('ui_patterns.sample.alert') }}</div>
        <div role="alert" class="alert alert-warning mt-2 text-sm">{{ __('ui_patterns.sample.alert') }}</div>
        <x-empty-state icon="search_off" :title="__('ui_patterns.sample.empty')" :message="__('ui_patterns.sample.hint')" compact framed />
    </x-card>
</x-index-page>
@endsection
