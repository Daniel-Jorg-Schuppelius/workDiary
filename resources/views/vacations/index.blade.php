@extends('layouts.app')

@section('nav-title', __('Urlaub'))

@section('content')
<x-page-shell>

    {{-- KPI-Tiles --}}
    <div class="grid grid-cols-1 gap-3 flex-none sm:grid-cols-3">
        <x-kpi-tile :label="__('Ausstehend')"       :value="$counts['pending']"  tone="warning" />
        <x-kpi-tile :label="__('Genehmigt (Jahr)')" :value="$counts['approved']" tone="success" />
        <x-kpi-tile :label="__('Gesamt (Jahr)')"    :value="$counts['total']"    tone="neutral" />
    </div>

    {{-- Filter --}}
    <x-filter-bar :action="route('vacations.index')" :reset="route('vacations.index')">
        @if ($isAdmin && $users->isNotEmpty())
            <x-filter-field :label="__('Mitarbeiter')" for="vac-user">
                <select id="vac-user" name="user_id" class="select select-bordered select-sm" onchange="this.form.submit()">
                    <option value="">{{ __('Alle Mitarbeiter') }}</option>
                    @foreach ($users as $u)
                        <option value="{{ $u['id'] ?? $u->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) ($u['id'] ?? $u->id))>{{ $u['name'] ?? $u->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        @endif

        <x-filter-field :label="__('Status')" for="vac-status">
            <select id="vac-status" name="status" class="select select-bordered select-sm" onchange="this.form.submit()">
                <option value="">{{ __('Alle Status') }}</option>
                @foreach (\App\Models\Vacation::$statuses as $s)
                    <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ __($s) }}</option>
                @endforeach
            </select>
        </x-filter-field>

        <x-filter-field :label="__('Typ')" for="vac-type">
            <select id="vac-type" name="type" class="select select-bordered select-sm" onchange="this.form.submit()">
                <option value="">{{ __('Alle Typen') }}</option>
                <option value="{{ \App\Models\Vacation::TYPE_VACATION }}" @selected(($filters['type'] ?? '') === \App\Models\Vacation::TYPE_VACATION)>{{ __('Urlaub') }}</option>
                <option value="{{ \App\Models\Vacation::TYPE_SICK }}"     @selected(($filters['type'] ?? '') === \App\Models\Vacation::TYPE_SICK)>{{ __('Krank') }}</option>
                <option value="{{ \App\Models\Vacation::TYPE_SPECIAL }}"  @selected(($filters['type'] ?? '') === \App\Models\Vacation::TYPE_SPECIAL)>{{ __('Sonderurlaub') }}</option>
                <option value="{{ \App\Models\Vacation::TYPE_UNPAID }}"   @selected(($filters['type'] ?? '') === \App\Models\Vacation::TYPE_UNPAID)>{{ __('Unbezahlt') }}</option>
            </select>
        </x-filter-field>
        <x-slot:extra>
            @can('create', \App\Models\Vacation::class)
                <a href="{{ route('vacations.create') }}?dialog=1"
                   class="btn btn-primary btn-sm"
                   data-entry-modal-trigger>
                    + {{ __('Neuer Antrag') }}
                </a>
            @endcan
        </x-slot:extra>
    </x-filter-bar>

    <x-table scroll="flex" :pinRows="true" :zebra="true" size="sm">
                <thead class="bg-base-200">
                    <tr>
                        @if ($isAdmin)
                            <th>{{ __('Mitarbeiter') }}</th>
                        @endif
                        <th>{{ __('Zeitraum') }}</th>
                        <th>{{ __('Tage') }}</th>
                        <th>{{ __('Typ') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Notiz') }}</th>
                        <th class="w-px"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vacations as $v)
                        @php
                            $days = $v->workingDays($holidayService);
                        @endphp
                        <tr class="hover">
                            @if ($isAdmin)
                                <td class="font-medium">{{ $v->user?->name ?? '–' }}</td>
                            @endif
                            <td class="whitespace-nowrap">
                                {{ $v->start_date->format('d.m.Y') }}
                                @if ($v->start_date->ne($v->end_date))
                                    – {{ $v->end_date->format('d.m.Y') }}
                                @endif
                            </td>
                            <td class="tabular-nums">{{ $days }}</td>
                            <td>
                                <span class="badge badge-sm badge-ghost">{{ $v->typeLabel() }}</span>
                            </td>
                            <td>
                                <span class="badge badge-sm badge-{{ $v->statusTone() }}">{{ $v->statusLabel() }}</span>
                                @if ($v->reject_reason)
                                    <span class="tooltip tooltip-right" data-tip="{{ $v->reject_reason }}">
                                        <svg class="inline h-3 w-3 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </span>
                                @endif
                            </td>
                            <td class="max-w-xs truncate text-base-content/60 text-xs">{{ $v->note }}</td>
                            <td>
                                <div class="flex items-center gap-1">
                                    {{-- Admin: approve/reject --}}
                                    @can('decide', $v)
                                        @if ($v->status === \App\Models\Vacation::STATUS_PENDING)
                                            <form method="POST" action="{{ route('vacations.approve', $v) }}" class="inline">
                                                @csrf @method('PATCH')
                                                <button type="submit"
                                                    title="{{ __('Genehmigen') }}"
                                                    class="btn btn-xs btn-ghost text-success">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                </button>
                                            </form>
                                            <a href="{{ route('vacations.reject-form', $v) }}?dialog=1"
                                               title="{{ __('Ablehnen') }}"
                                               class="btn btn-xs btn-ghost text-error"
                                               data-entry-modal-trigger>
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </a>
                                        @endif
                                    @endcan

                                    {{-- Edit --}}
                                    @can('update', $v)
                                        <a href="{{ route('vacations.edit', $v) }}?dialog=1"
                                           title="{{ __('Bearbeiten') }}"
                                           class="btn btn-xs btn-ghost"
                                           data-entry-modal-trigger>
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 012.828 2.828L11.828 15.828a2 2 0 01-1.414.586H9v-2.414z"/></svg>
                                        </a>
                                    @endcan

                                    {{-- Stornieren --}}
                                    @can('cancel', $v)
                                        <form method="POST" action="{{ route('vacations.cancel', $v) }}" class="inline"
                                              data-confirm-dialog
                                              data-confirm-message="{{ __('Urlaubsantrag wirklich stornieren?') }}"
                                              data-confirm-label="{{ __('Stornieren') }}">
                                            @csrf @method('PATCH')
                                            <button type="submit"
                                                title="{{ __('Stornieren') }}"
                                                class="btn btn-xs btn-ghost text-warning">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                            </button>
                                        </form>
                                    @endcan

                                    {{-- Delete --}}
                                    @can('delete', $v)
                                        <form method="POST" action="{{ route('vacations.destroy', $v) }}" class="inline"
                                              data-confirm-dialog
                                              data-confirm-message="{{ __('Urlaubsantrag wirklich löschen?') }}"
                                              data-confirm-label="{{ __('Löschen') }}">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                title="{{ __('Löschen') }}"
                                                class="btn btn-xs btn-ghost text-error">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4h6v3m-7 0h8"/></svg>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">beach_access</span>' :colspan="$isAdmin ? 7 : 6" :title="__('Keine Einträge gefunden')" compact />
                    @endforelse
                </tbody>
    </x-table>

    {{-- Pagination --}}
    @if ($vacations->hasPages())
        <div class="flex-none">{{ $vacations->links() }}</div>
    @endif

</x-page-shell>
@endsection
