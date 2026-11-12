{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Nachweistypen der Organisation (Feature 117, MVP-1586): Katalog als Vorlage, eigene Typen änderbar. --}}
@extends('layouts.app')

@section('title', __('procurement.credential_types.title'))
@section('nav-title', __('procurement.credential_types.title'))

@include('partials.page-fill')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('procurement.credential_types.subtitle')">
        <x-slot:actions>
            <x-icon-btn icon="verified_user" size="sm" :href="route('suppliers.credentials.index')" show-label>{{ __('procurement.credentials.title') }}</x-icon-btn>
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('suppliers.credential-types.create')" show-label>{{ __('procurement.credential_types.action.create') }}</x-icon-btn>
        </x-slot:actions>

        <x-table scroll="flex" :pin-rows="true" :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('procurement.credential_types.field.name') }}</th>
                    <th>{{ __('procurement.credential_types.field.origin') }}</th>
                    <th>{{ __('procurement.credential_types.field.required') }}</th>
                    <th>{{ __('procurement.credential_types.field.blocking_mode') }}</th>
                    <th class="text-right">{{ __('procurement.credential_types.field.validity') }}</th>
                    <th>{{ __('procurement.credential_types.field.state') }}</th>
                    <th class="text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($types as $type)
                <tr>
                    <td>
                        {{ $type->name }}
                        @if ($type->description)<div class="text-xs text-muted">{{ $type->description }}</div>@endif
                    </td>
                    <td><x-status-badge :tone="$type->isTemplate() ? 'neutral' : 'info'">{{ $type->isTemplate() ? __('procurement.credential_types.origin.catalog') : __('procurement.credential_types.origin.own') }}</x-status-badge></td>
                    <td class="text-sm">
                        @if (! $type->is_required_default)
                            {{ __('procurement.credential_types.required.optional') }}
                        @elseif (($type->required_tag_ids ?? []) === [])
                            {{ __('procurement.credential_types.required.all') }}
                        @else
                            {{ __('procurement.credential_types.required.tags', ['tags' => collect($type->required_tag_ids)->map(fn ($id) => $tags->get($id)?->name)->filter()->implode(', ')]) }}
                        @endif
                    </td>
                    <td>{{ __('procurement.credential_types.mode.' . $type->blocking_mode) }}</td>
                    <td class="text-right tabular-nums">{{ $type->default_validity_months !== null ? __('procurement.credential_types.months', ['count' => $type->default_validity_months]) : '—' }}</td>
                    <td><x-status-badge :tone="$type->is_active ? 'success' : 'neutral'">{{ $type->is_active ? __('procurement.credential_types.state.active') : __('procurement.credential_types.state.inactive') }}</x-status-badge></td>
                    <td class="text-right whitespace-nowrap">
                        @if ($type->isTemplate())
                            <x-icon-btn icon="content_copy" size="xs" data-entry-modal-trigger :href="route('suppliers.credential-types.create', ['vorlage' => $type->sqid])" :label="__('procurement.credential_types.action.adopt')" />
                        @else
                            <x-icon-btn icon="edit" size="xs" data-entry-modal-trigger :href="route('suppliers.credential-types.edit', $type)" :label="__('Bearbeiten')" />
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="7" icon="verified_user" :title="__('procurement.credential_types.empty')" />
            @endforelse
        </x-table>
        <x-pagination :paginator="$types" standing />
    </x-index-page>
@endsection
