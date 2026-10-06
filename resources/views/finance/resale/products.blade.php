{{--
  Created on   : Mon Sep 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : products.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Produkt-Einstufung (Feature 152): welche Lexoffice-Artikel und lokalen
  Artikel Abo-Produkte sind. Erkannt über den Namen, je Artikel übersteuerbar
  — „nie Abo-Position" hält Dienstleistungen mit Microsoft im Namen aus
  Vorschlägen und Listen. Lokale Artikel stehen vor der Voll-Höhe-Tabelle (R5).
--}}
@extends('layouts.app')
@section('title', __('resale.products.title'))
@section('nav-title', __('resale.title.menu'))
@include('partials.page-fill')

@php
    $canManage = auth()->user()?->can(\App\Enums\User\Permission::ResellingManage->value) ?? false;
@endphp

@section('content')
    <x-index-page overflow="clip" :title="__('resale.products.title')" :subtitle="__('resale.products.subtitle')">

        @include('finance.resale._tabs')
        {{-- Serienrechnung bei lokaler Rechnungshoheit (Feature 152): Org-Schalter + Vorlauf; der Lauf ist resale:draft-local. --}}
        <x-form-group :legend="__('resale.auto_draft.title')" icon="event_repeat" tone="info" cols="1" compact class="mb-3"
                      :description="__('resale.auto_draft.description')">
            @if ($canManage)
                <form method="POST" action="{{ route('finance.resale.auto-draft.store') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <x-checkbox-field name="auto_local_drafts" tone="info"
                                     :label="__('resale.auto_draft.enabled')"
                                     :checked="(string) old('auto_local_drafts', $autoDrafts ? '1' : '0') === '1'" />
                    <x-input-field name="lead_days" type="number" min="0" max="90" step="1" inputmode="numeric"
                                   :label="__('resale.auto_draft.lead_days')"
                                   :value="old('lead_days', $autoDraftLeadDays)"
                                   :hint="__('resale.auto_draft.lead_days_hint')" />
                    <x-icon-btn icon="save" size="sm" tone="primary" type="submit" show-label>{{ __('resale.auto_draft.save') }}</x-icon-btn>
                </form>
            @else
                <p class="text-sm">{{ $autoDrafts ? __('resale.auto_draft.state_on', ['days' => $autoDraftLeadDays]) : __('resale.auto_draft.state_off') }}</p>
            @endif
        </x-form-group>
        <p class="text-xs text-muted mb-2">{{ __('resale.products.hint') }}</p>
        {{-- Einstufung je Katalogartikel (MVP-1025): alle Quellen in einer Liste — Artikelstamm, Lexoffice, … --}}
        <x-table scroll="flex" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('resale.field.article') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('resale.products.source') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('resale.products.number') }}</x-table.th>
                    <x-table.th>{{ __('resale.products.unit') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('resale.products.price') }}</x-table.th>
                    <x-table.th class="text-right" sort type="number">{{ __('resale.products.subscriptions') }}</x-table.th>
                    <x-table.th>{{ __('resale.products.detected') }}</x-table.th>
                    <x-table.th>{{ __('resale.products.override') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                @php $article = $row['article']; @endphp
                <tr @class(['hover', 'opacity-70' => ! $row['effective']])>
                    <td class="font-medium">{{ $article->name }}</td>
                    <td class="text-sm">{{ $article->sourceLabel }}</td>
                    <td class="font-mono text-xs">{{ $article->number ?? '—' }}</td>
                    <td class="text-sm">{{ $article->unitName ?? '—' }}</td>
                    <td class="text-right tabular-nums whitespace-nowrap">{{ $article->netPrice?->withScale(2)->format() ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $row['subscriptions'] }}</td>
                    <td><x-status-badge size="xs" :tone="$row['detected']->tone()" :label="$row['detected']->label()" /></td>
                    <td>
                        @if ($canManage)
                            <form method="POST" action="{{ route('finance.resale.products.store') }}" class="flex items-center gap-1">
                                @csrf
                                <input type="hidden" name="article" value="{{ $article->formKey }}">
                                <select name="role" class="select select-xs select-bordered w-44" aria-label="{{ __('resale.products.override') }}">
                                    <option value="auto" @selected($row['role'] === null)>{{ __('resale.products.role.auto') }}</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->value }}" @selected($row['role'] === $role)>{{ $role->label() }}</option>
                                    @endforeach
                                </select>
                                <x-icon-btn icon="save" size="xs" tone="ghost" type="submit" :title="__('resale.products.save')" />
                            </form>
                        @else
                            {{ $row['role']?->label() ?? __('resale.products.role.auto') }}
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="8" icon="inventory_2" :title="__('resale.products.empty')" compact />
            @endforelse
        </x-table>
    </x-index-page>
@endsection
