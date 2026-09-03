{{--
  Created on   : Thu Sep 03 2026
  Author       : Daniel Jörg Schuppelius
  License      : AGPL-3.0-or-later

  Lizenz-Reselling-Abgleich (Feature 151, MVP-757): Liste der Läufe mit
  Kennzahlen; neuer Lauf über den Upload-Dialog.
--}}

@extends('layouts.app')

@section('title', __('reselling.title.index'))
@section('nav-title', __('reselling.title.menu'))

@section('content')
    <x-index-page :subtitle="__('reselling.subtitle')">
        <x-slot:actions>
            <x-icon-btn icon="upload" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('finance.reselling.create')"
                        show-label>{{ __('reselling.action.new') }}</x-icon-btn>
        </x-slot:actions>

        <x-table>
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('reselling.field.created') }}</x-table.th>
                    <x-table.th>{{ __('reselling.field.status') }}</x-table.th>
                    <x-table.th>{{ __('reselling.field.sources') }}</x-table.th>
                    <x-table.th>{{ __('reselling.field.reference') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('reselling.field.periods') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('reselling.field.problems') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('reselling.field.open_fee') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('reselling.field.price_flags') }}</x-table.th>
                    <x-table.th></x-table.th>
                </tr>
            </x-slot:head>

            @forelse ($runs as $run)
                @php
                    $summary = $run->summary ?? [];
                    $kinds = array_values(array_unique(array_map(static fn(array $file): string => (string) ($file['kind'] ?? ''), $run->files ?? [])));
                @endphp
                <tr>
                    <td>
                        <a class="link" href="{{ route('finance.reselling.show', $run->sqid) }}">{{ $run->created_at?->format('d.m.Y H:i') }}</a>
                        @if ($run->creator)
                            <div class="text-xs text-muted">{{ $run->creator->name }}</div>
                        @endif
                    </td>
                    <td><x-status-badge :tone="$run->status->tone()" :label="$run->status->label()" /></td>
                    <td class="text-sm">
                        @foreach ($kinds as $kind)
                            @if (in_array($kind, [\App\Models\Reselling\ReconciliationRun::KIND_TELEKOM, \App\Models\Reselling\ReconciliationRun::KIND_QUALITYHOSTING], true))
                                <span class="badge badge-ghost badge-sm">{{ __('reselling.source.' . $kind) }}</span>
                            @elseif ($kind === \App\Models\Reselling\ReconciliationRun::KIND_PRICELIST)
                                <span class="badge badge-ghost badge-sm">{{ __('reselling.section.price') }}</span>
                            @endif
                        @endforeach
                    </td>
                    <td>{{ $run->reference_date->format('d.m.Y') }}</td>
                    <td class="text-right">{{ $summary['periods'] ?? '—' }}</td>
                    <td class="text-right">
                        @if (($summary['problems'] ?? 0) > 0)
                            <span class="text-warning font-medium">{{ $summary['problems'] }}</span>
                        @else
                            {{ isset($summary['problems']) ? 0 : '—' }}
                        @endif
                    </td>
                    <td class="text-right">{{ $summary['open_fee']['formatted'] ?? '—' }}</td>
                    <td class="text-right">{{ $summary['price_flags'] ?? '—' }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="visibility" size="xs" tone="ghost"
                                        :href="route('finance.reselling.show', $run->sqid)"
                                        :title="__('reselling.title.show')" />
                            @if ($run->status === \App\Enums\Reselling\ReconciliationRunStatus::Done)
                                <x-icon-btn icon="download" size="xs" tone="ghost"
                                            :href="route('finance.reselling.download', $run->sqid)"
                                            :title="__('reselling.action.download')" />
                            @endif
                            <form method="POST" action="{{ route('finance.reselling.destroy', $run->sqid) }}" data-confirm="{{ __('reselling.action.delete') }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-btn icon="delete" size="xs" tone="ghost" type="submit" :title="__('reselling.action.delete')" />
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="9" :title="__('reselling.empty.runs')" />
            @endforelse
        </x-table>
    </x-index-page>
@endsection
