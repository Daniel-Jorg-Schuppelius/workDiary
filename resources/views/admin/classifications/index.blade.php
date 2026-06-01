@extends('layouts.app')

@section('title', __('Klassifikationen'))
@section('nav-title', __('Klassifikationen'))

@section('content')
<x-index-page :subtitle="__('Plattform-Defaults vergleichen und organisationsspezifische Werte pflegen für :org.', ['org' => $organization->name])">
    <x-slot:actions>
        <x-icon-btn icon="upload" size="sm"
                    data-entry-modal-trigger
                    :href="route('admin.classifications.import.form')"
                    show-label>{{ __('CSV-Import') }}</x-icon-btn>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('admin.classifications.create')"
                    show-label>{{ __('Klassifikation anlegen') }}</x-icon-btn>
    </x-slot:actions>

    @foreach ($domains as $domain)
        @php
            /** @var \Illuminate\Support\Collection<int, \App\Models\Classification> $platformRows */
            /** @var \Illuminate\Support\Collection<int, \App\Models\Classification> $orgRows */
            $platformRows = $platformByDomain->get($domain->value, collect());
            $orgRows = $orgByDomain->get($domain->value, collect());
            $orgRowsByCode = $orgRows->keyBy('code');
        @endphp
        <section class="space-y-4">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">{{ $domainLabels[$domain->value] ?? $domain->value }}</h2>
                    <p class="text-sm text-base-content/60">{{ __('Domain: :domain', ['domain' => $domain->value]) }}</p>
                </div>
                <x-icon-btn icon="add_circle" size="xs" tone="primary"
                            data-entry-modal-trigger
                            :href="route('admin.classifications.create', ['domain' => $domain->value])"
                            show-label>{{ __('Org-Wert anlegen') }}</x-icon-btn>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <section class="space-y-3">
                    <h3 class="text-base font-semibold">{{ __('Organisationswerte') }}</h3>
                    <form id="classification-reorder-{{ $domain->value }}" method="POST" action="{{ route('admin.classifications.reorder', $domain->value) }}">
                        @csrf
                    </form>
                    <x-table>
                        <x-slot:head>
                            <tr>
                                <th>{{ __('Code') }}</th>
                                <th>{{ __('Bezeichnung') }}</th>
                                <th>{{ __('Sortierung') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th></th>
                            </tr>
                        </x-slot:head>
                        @forelse ($orgRows as $classification)
                            <tr>
                                <td class="font-mono text-sm">{{ $classification->code }}</td>
                                <td>
                                    <div class="font-medium">{{ $classification->label }}</div>
                                    @if ($classification->description)
                                        <div class="text-xs text-base-content/60">{{ $classification->description }}</div>
                                    @endif
                                </td>
                                <td>{{ $classification->sort_order }}</td>
                                <td>
                                    <input type="number"
                                           form="classification-reorder-{{ $domain->value }}"
                                           name="sort_map[{{ $classification->id }}]"
                                           value="{{ old('sort_map.' . $classification->id, $classification->sort_order) }}"
                                           class="input input-bordered input-sm w-24"
                                           min="0"
                                           max="100000" />
                                </td>
                                <td>
                                    <x-status-badge size="xs" :tone="$classification->active ? 'success' : 'ghost'">
                                        {{ $classification->active ? __('Aktiv') : __('Inaktiv') }}
                                    </x-status-badge>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <x-icon-btn icon="edit" size="xs"
                                                data-entry-modal-trigger
                                                :href="route('admin.classifications.edit', $classification)"
                                                :title="__('Bearbeiten')" />
                                    <form method="POST" action="{{ route('admin.classifications.destroy', $classification) }}" class="inline">
                                        @csrf @method('DELETE')
                                        <x-icon-btn type="submit" icon="delete" size="xs" tone="error"
                                                    :title="__('Löschen')"
                                                    data-confirm="{{ __('Klassifikation wirklich löschen?') }}" />
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-base-content/60 py-6">{{ __('Noch keine organisationsspezifischen Werte vorhanden.') }}</td>
                            </tr>
                        @endforelse
                    </x-table>
                    @if ($orgRows->isNotEmpty())
                        <div class="flex justify-end">
                            <button type="submit" form="classification-reorder-{{ $domain->value }}" class="btn btn-sm btn-outline gap-2">
                                <x-icon name="swap_vert" /> {{ __('Reihenfolge speichern') }}
                            </button>
                        </div>
                    @endif
                </section>

                <section class="space-y-3">
                    <h3 class="text-base font-semibold">{{ __('Plattform-Defaults') }}</h3>
                    <x-table>
                        <x-slot:head>
                            <tr>
                                <th>{{ __('Code') }}</th>
                                <th>{{ __('Standard') }}</th>
                                <th>{{ __('Org-Stand') }}</th>
                                <th></th>
                            </tr>
                        </x-slot:head>
                        @forelse ($platformRows as $classification)
                            @php $override = $orgRowsByCode->get($classification->code); @endphp
                            <tr>
                                <td class="font-mono text-sm">{{ $classification->code }}</td>
                                <td>
                                    <div class="font-medium">{{ $classification->label }}</div>
                                    <div class="text-xs text-base-content/60">{{ __('Sortierung: :sort', ['sort' => $classification->sort_order]) }}</div>
                                </td>
                                <td>
                                    @if ($override)
                                        <div class="font-medium">{{ $override->label }}</div>
                                        <x-status-badge size="xs" :tone="$override->active ? 'info' : 'warning'">
                                            {{ $override->active ? __('Override') : __('Deaktiviert') }}
                                        </x-status-badge>
                                    @else
                                        <span class="text-sm text-base-content/60">{{ __('Kein Override') }}</span>
                                    @endif
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <x-icon-btn icon="edit_square" size="xs"
                                                data-entry-modal-trigger
                                                :href="route('admin.classifications.create', ['source' => $classification->sqid])"
                                                :title="__('Override anlegen')" />
                                    <form method="POST" action="{{ route('admin.classifications.deactivate-default', $classification) }}" class="inline">
                                        @csrf
                                        <x-icon-btn type="submit" icon="block" size="xs" tone="warning"
                                                    :title="__('Standard deaktivieren')"
                                                    data-confirm="{{ __('Plattform-Default für diese Organisation deaktivieren?') }}" />
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-base-content/60 py-6">{{ __('Keine Plattform-Defaults vorhanden.') }}</td>
                            </tr>
                        @endforelse
                    </x-table>
                </section>
            </div>
        </section>
    @endforeach
</x-index-page>
@endsection
