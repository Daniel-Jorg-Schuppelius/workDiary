{{--
  Created on   : Thu May 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Zählerstände'))
@section('nav-title', __('Zählerstände'))

@section('content')
<x-index-page :subtitle="__('Erfasste Zählerstände & Verbrauchswerte.')">
    <x-slot:actions>
        @if ($canCreate)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        :href="route('meter-readings.create')"
                        show-label>{{ __('Stand erfassen') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    @if ($readings->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">speed</span>' />
    @else
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('Ablesezeit') }}</th>
                    <th>{{ __('Asset / Zähler') }}</th>
                    <th class="text-right">{{ __('Stand') }}</th>
                    <th class="text-right">{{ __('Verbrauch') }}</th>
                    <th>{{ __('Einheit') }}</th>
                    <th>{{ __('Erfasst von') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($readings as $r)
                <tr class="hover">
                    <td class="font-mono text-xs">{{ $r->read_at?->translatedFormat('d.m.Y H:i') }}</td>
                    <td>
                        @if ($r->asset)
                            <span class="material-symbols-outlined text-[14px] align-middle">speed</span>
                            {{ $r->asset->name }}
                            <span class="text-base-content/50 text-xs">{{ $r->asset->asset_no }}</span>
                        @endif
                    </td>
                    <td class="text-right font-mono">{{ number_format((float) $r->value, 4, ',', '.') }}</td>
                    <td class="text-right font-mono text-base-content/70">
                        {{ $r->consumption !== null ? number_format((float) $r->consumption, 4, ',', '.') : '—' }}
                    </td>
                    <td>{{ $r->unit }}</td>
                    <td class="text-base-content/70 text-xs">{{ $r->readBy?->name ?: '—' }}</td>
                </tr>
            @endforeach
        </x-table>

        @if ($readings->hasPages())
            <div class="px-1">{{ $readings->links() }}</div>
        @endif
    @endif
</x-index-page>
@endsection
