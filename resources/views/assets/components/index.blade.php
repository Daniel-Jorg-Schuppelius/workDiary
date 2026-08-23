{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Anlagen-Stückliste (Feature 118, MVP-607): was in DIESEM Gerät verbaut ist,
  inklusive Historie der ersetzten Teile.
--}}

@extends('layouts.app')

@section('title', __('asset.components.title'))
@section('nav-title', __('asset.components.title'))

@section('content')
    <x-index-page :subtitle="$asset->name">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('assets.components.create', $asset)"
                        show-label>{{ __('asset.components.action.add') }}</x-icon-btn>
        </x-slot:actions>

        @if ($due->isNotEmpty())
            {{-- Fällige Verschleißteile: Vorschlag für den nächsten Einsatz,
                 damit der zweite Anfahrtsweg entfällt. --}}
            <div class="rounded-box border border-warning/40 bg-warning/5 px-4 py-3">
                <p class="text-sm font-medium">{{ __('asset.components.due.heading') }}</p>
                <p class="mt-1 text-xs text-base-content/70">{{ __('asset.components.due.hint') }}</p>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($due as $part)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ $part->displayName() }}</span>
                            @if ($part->position !== null)<span class="text-base-content/60">{{ $part->position }}</span>@endif
                            <x-status-badge tone="warning" outline>{{ optional($part->dueOn())->fdate() ?? '—' }}</x-status-badge>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-table scroll="flex" :pin-rows="true" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('asset.components.column.name') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('asset.components.column.position') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('asset.components.column.quantity') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('asset.components.column.serial_no') }}</x-table.th>
                    <x-table.th sort type="date">{{ __('asset.components.column.installed_on') }}</x-table.th>
                    <x-table.th sort type="date">{{ __('asset.components.column.due_on') }}</x-table.th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($installed as $part)
                <tr class="hover">
                    <td class="font-medium">{{ $part->displayName() }}</td>
                    <td>{{ $part->position ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $part->quantity, 2) }} {{ $part->unit }}</td>
                    <td>{{ $part->serial_no ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ optional($part->installed_on)->fdate() ?? '—' }}</td>
                    <td class="whitespace-nowrap">
                        @php($due = $part->dueOn())
                        @if ($due === null)
                            <span class="text-base-content/50">—</span>
                        @else
                            <x-status-badge :tone="$part->isDue() ? 'warning' : 'neutral'" outline>{{ $due->fdate() }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="swap_horiz" size="xs" tone="ghost"
                                        data-entry-modal-trigger
                                        :href="route('assets.components.replace-form', [$asset, $part])"
                                        :label="__('asset.components.action.replace')" />
                            <x-action-form :action="route('assets.components.remove', [$asset, $part])">
                                <x-icon-btn icon="remove_circle" size="xs" tone="ghost" type="submit"
                                            :label="__('asset.components.action.remove')" />
                            </x-action-form>
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="7" icon="build" :title="__('asset.components.empty')" compact />
            @endforelse
        </x-table>

        @if ($history->count() > $installed->count())
            <details class="rounded-box border border-base-300 bg-base-100 shadow-xs">
                <summary class="cursor-pointer px-4 py-3 text-sm font-medium">{{ __('asset.components.history.heading') }}</summary>
                <div class="px-4 pb-4">
                    <x-table :bare="true">
                        <x-slot:head>
                            <tr>
                                <th>{{ __('asset.components.column.name') }}</th>
                                <th>{{ __('asset.components.column.installed_on') }}</th>
                                <th>{{ __('asset.components.column.removed_on') }}</th>
                                <th>{{ __('asset.components.column.status') }}</th>
                            </tr>
                        </x-slot:head>
                        @foreach ($history->where('status', '!=', \App\Models\AssetComponent::STATUS_INSTALLED) as $part)
                            <tr class="hover">
                                <td>{{ $part->displayName() }}</td>
                                <td>{{ optional($part->installed_on)->fdate() ?? '—' }}</td>
                                <td>{{ optional($part->removed_on)->fdate() ?? '—' }}</td>
                                <td>{{ __('asset.components.status.' . $part->status) }}</td>
                            </tr>
                        @endforeach
                    </x-table>
                </div>
            </details>
        @endif
    </x-index-page>
@endsection
