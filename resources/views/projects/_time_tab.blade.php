{{-- Tab: Zeiterfassung — erwartet: $project, $timeEntries, $totalMinutes, $monthMinutes, $myMinutes --}}
@php
    $fmt = fn(int $min) => intdiv($min, 60) . ':' . str_pad($min % 60, 2, '0', STR_PAD_LEFT) . ' h';
@endphp

<div class="flex flex-col gap-3">
    {{-- Summary --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 text-center shadow-xs">
            <div class="font-['Space_Grotesk'] text-2xl font-bold">{{ $fmt($totalMinutes) }}</div>
            <div class="mt-1 text-xs text-base-content/60">{{ __('Gesamt') }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 text-center shadow-xs">
            <div class="font-['Space_Grotesk'] text-2xl font-bold">{{ $fmt($monthMinutes) }}</div>
            <div class="mt-1 text-xs text-base-content/60">{{ __('Dieser Monat') }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 text-center shadow-xs">
            <div class="font-['Space_Grotesk'] text-2xl font-bold">{{ $fmt($myMinutes) }}</div>
            <div class="mt-1 text-xs text-base-content/60">{{ __('Meine Stunden') }}</div>
        </div>
    </div>

    {{-- Tabelle --}}
    <div class="rounded-box border border-base-300 bg-base-100 shadow-xs">
        <header class="flex items-center justify-between border-b border-base-300 px-4 py-3">
            <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Zeiteinträge') }}</span>
            @can('create', \App\Models\TimeEntry::class)
                <a href="{{ route('projects.time-entries.create', $project) }}"
                   data-entry-modal-trigger class="btn btn-sm btn-primary">+ {{ __('Zeiteintrag') }}</a>
            @endcan
        </header>
        @if ($timeEntries->isEmpty())
            <div class="px-4 py-8 text-center text-sm text-base-content/60">{{ __('Noch keine Zeiteinträge erfasst.') }}</div>
        @else
            <div class="overflow-x-auto">
                <table class="table table-sm" data-sortable>
                    <thead>
                        <tr class="text-xs text-base-content/50">
                            <th data-sort data-sort-type="date" data-sort-default="desc">{{ __('Datum') }}</th>
                            <th data-sort>{{ __('Mitarbeitende') }}</th>
                            <th class="text-right" data-sort data-sort-type="number">{{ __('Zeit') }}</th>
                            <th data-sort>{{ __('Aufgabe') }}</th>
                            <th data-sort>{{ __('Beschreibung') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($timeEntries as $entry)
                            <tr class="hover:bg-base-200/50">
                                <td class="whitespace-nowrap text-xs">{{ $entry->date->format('d.m.Y') }}</td>
                                <td class="text-xs">{{ $entry->user->name ?? '—' }}</td>
                                <td class="whitespace-nowrap text-right text-xs font-medium">{{ $entry->hoursFormatted() }}</td>
                                <td class="text-xs text-base-content/70">{{ $entry->task->title ?? '—' }}</td>
                                <td class="max-w-xs truncate text-xs text-base-content/70">{{ $entry->description }}</td>
                                <td class="whitespace-nowrap">
                                    @can('update', $entry)
                                        <a href="{{ route('projects.time-entries.edit', [$project, $entry]) }}"
                                           data-entry-modal-trigger class="btn btn-xs btn-ghost">{{ __('Edit') }}</a>
                                    @endcan
                                    @can('delete', $entry)
                                        <form method="POST" action="{{ route('projects.time-entries.destroy', [$project, $entry]) }}"
                                              data-confirm-dialog
                                              data-confirm-title="{{ __('Zeiteintrag löschen') }}"
                                              data-confirm-label="{{ __('Löschen') }}"
                                              class="inline">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-xs btn-ghost text-error">{{ __('Del') }}</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
