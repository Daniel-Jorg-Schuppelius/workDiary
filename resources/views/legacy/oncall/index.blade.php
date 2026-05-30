@extends('layouts.app')
@section('title', __('Bereitschaft') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Bereitschaft'))

@section('content')
@php
    /** @var \Illuminate\Support\Collection<int, \App\Legacy\Models\LegacyUser> $users */
    /** @var array<string, mixed> $filters */
@endphp
<div class="flex h-[calc(100dvh-11rem)] flex-col gap-4">
    <div role="tablist" class="tabs tabs-box flex-none self-start">
        <a role="tab" href="{{ route('legacy.oncall.index') }}" class="tab tab-active">{{ __('Bereitschaft') }}</a>
        <a role="tab" href="{{ route('legacy.notdienst.index') }}" class="tab">{{ __('Notdienst') }}</a>
    </div>
    <x-filter-bar :action="route('legacy.oncall.index')">
            @if ($isAdmin)
                <div class="flex flex-col min-w-48">
                    <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Mitarbeiter') }}</span></label>
                    <select name="user" class="select select-bordered select-sm w-full">
                        <option value="">{{ __('Alle') }}</option>
                        @foreach ($users as $legacyUser)
                            @php($legacySqid = \App\Support\Sqid::encode(\App\Legacy\Models\LegacyUser::class, $legacyUser->id))
                            <option value="{{ $legacySqid }}" @selected((string) ($filters['user'] ?? '') === $legacySqid)>{{ $legacyUser->uname }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <x-date-range :from="$filters['from'] ?? ''" :to="$filters['to'] ?? ''" />
            <x-icon-btn icon="filter_alt" tone="primary" size="sm" type="submit" show-label>{{ __('Filtern') }}</x-icon-btn>
            @if (array_filter($filters))
                <x-icon-btn icon="restart_alt" size="sm" :href="route('legacy.oncall.index')" show-label>{{ __('Zurücksetzen') }}</x-icon-btn>
            @endif
            @if ($isAdmin)
                <x-icon-btn icon="add" tone="outline" size="sm"
                            data-entry-modal-trigger
                            :href="route('legacy.oncall.create')"
                            show-label>{{ __('Neue Bereitschaft') }}</x-icon-btn>
            @endif
    </x-filter-bar>

    @php
        $kpiTiles = [
            ['all',      __('Gesamt'),       'border-base-300'],
            ['today',    __('Heute aktiv'),  'border-primary/40'],
            ['upcoming', __('Kommend'),      'border-info/40'],
            ['past',     __('Vergangen'),    'border-neutral/40'],
        ];
    @endphp
    <div class="flex-none grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @php
            $toneMap = ['border-base-300' => 'neutral', 'border-primary/40' => 'primary', 'border-info/40' => 'info', 'border-neutral/40' => 'neutral'];
        @endphp
        @foreach ($kpiTiles as [$key, $label, $borderClass])
            <x-kpi-tile :label="$label" :value="$counts[$key] ?? 0" :tone="$toneMap[$borderClass] ?? 'neutral'" />
        @endforeach
    </div>

    <x-table size="xs" :pin-rows="true" scroll="flex">
                <thead class="bg-base-200">
                <tr>
                    <th class="w-16 text-center">ID</th>
                    <th>{{ __('Mitarbeiter') }}</th>
                    <th class="w-32 text-center">{{ __('Von') }}</th>
                    <th class="w-32 text-center">{{ __('Bis') }}</th>
                    <th class="w-32 text-right">{{ __('Aktion') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr class="hover">
                        <td class="text-center">{{ $item->id }}</td>
                        <td>{{ optional($item->mitarbeiter)->uname ?? __('Unbekannt') }}</td>
                        <td class="bg-info/10 text-center">{{ $item->von?->format('d.m.Y') ?? '-' }}</td>
                        <td class="bg-info/10 text-center">{{ $item->bis?->format('d.m.Y') ?? '-' }}</td>
                        <td class="text-right whitespace-nowrap">
                            @if ($isAdmin)
                                <div class="inline-flex items-center justify-end gap-1 whitespace-nowrap">
                                    <x-icon-btn icon="edit"
                                                data-entry-modal-trigger
                                                :href="route('legacy.oncall.edit', $item)"
                                                :label="__('Bearbeiten')" />
                                    <form method="POST" action="{{ route('legacy.oncall.destroy', $item) }}" class="inline"
                                          data-confirm-dialog
                                          data-confirm-message="{{ __('Eintrag wirklich löschen?') }}"
                                          data-confirm-label="{{ __('Löschen') }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">medical_services</span>' :colspan="5" :title="__('Keine Bereitschaftseinträge gefunden')" compact />
                @endforelse
            </tbody>
    </x-table>

    <div class="flex-none rounded-box border border-base-300 bg-base-100 px-3 py-2 shadow-xs">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-xs text-base-content/60">
                {{ __('Seite') }} {{ $items->currentPage() }} / {{ $items->lastPage() }}
                ({{ $items->total() }} {{ __('Einträge') }})
            </span>
            @if ($items->hasPages())
                {{ $items->links('vendor.pagination.daisyui-simple') }}
            @endif
        </div>
    </div>
</div>
@endsection
