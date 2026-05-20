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
                @foreach (\App\Enums\Vacation\VacationStatus::options() as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-filter-field>

        <x-filter-field :label="__('Typ')" for="vac-type">
            <select id="vac-type" name="type" class="select select-bordered select-sm" onchange="this.form.submit()">
                <option value="">{{ __('Alle Typen') }}</option>
                @foreach (\App\Enums\Vacation\VacationType::options() as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-slot:extra>
            @can('create', \App\Models\Vacation::class)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('vacations.create') . '?dialog=1'"
                            show-label>{{ __('Neuer Antrag') }}</x-icon-btn>
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
                                        <x-icon name="info" class="text-error" />
                                    </span>
                                @endif
                            </td>
                            <td class="max-w-xs truncate text-base-content/60 text-xs">{{ $v->note }}</td>
                            <td>
                                <div class="flex items-center gap-1">
                                    @can('decide', $v)
                                        @if ($v->status === \App\Enums\Vacation\VacationStatus::Pending)
                                            <form method="POST" action="{{ route('vacations.approve', $v) }}" class="inline">
                                                @csrf @method('PATCH')
                                                <x-icon-btn icon="check" tone="success" type="submit" :label="__('Genehmigen')" />
                                            </form>
                                            <x-icon-btn icon="close" tone="error"
                                                        data-entry-modal-trigger
                                                        :href="route('vacations.reject-form', $v) . '?dialog=1'"
                                                        :label="__('Ablehnen')" />
                                        @endif
                                    @endcan

                                    @can('update', $v)
                                        <x-icon-btn icon="edit"
                                                    data-entry-modal-trigger
                                                    :href="route('vacations.edit', $v) . '?dialog=1'"
                                                    :label="__('Bearbeiten')" />
                                    @endcan

                                    @can('cancel', $v)
                                        <form method="POST" action="{{ route('vacations.cancel', $v) }}" class="inline"
                                              data-confirm-dialog
                                              data-confirm-message="{{ __('Urlaubsantrag wirklich stornieren?') }}"
                                              data-confirm-label="{{ __('Stornieren') }}">
                                            @csrf @method('PATCH')
                                            <x-icon-btn icon="block" tone="warning" type="submit" :label="__('Stornieren')" />
                                        </form>
                                    @endcan

                                    @can('delete', $v)
                                        <form method="POST" action="{{ route('vacations.destroy', $v) }}" class="inline"
                                              data-confirm-dialog
                                              data-confirm-message="{{ __('Urlaubsantrag wirklich löschen?') }}"
                                              data-confirm-label="{{ __('Löschen') }}">
                                            @csrf @method('DELETE')
                                            <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
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
