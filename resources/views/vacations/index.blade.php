{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@php
/**
 * @var \Illuminate\Support\Collection<int, \App\Models\User>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @var bool $isAdmin
 * @var array<string, mixed> $counts
 * @var array<string, mixed> $filters
 */
@endphp

@section('nav-title', __('Urlaub'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Urlaubsanträge, Genehmigungen und Resturlaub im Überblick.')">

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
                <select id="vac-user" name="user_id" class="select select-bordered select-sm" data-autosubmit>
                    <option value="">{{ __('Alle Mitarbeiter') }}</option>
                    @foreach ($users as $u)
                        @php
                            $uid = (int) ($u['id'] ?? $u->id);
                            $usqid = \App\Support\Sqid::encode(\App\Models\User::class, $uid);
                        @endphp
                        <option value="{{ $usqid }}" @selected((string) ($filters['user_id'] ?? '') === $usqid)>{{ $u['name'] ?? $u->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        @endif

        <x-filter-field :label="__('Status')" for="vac-status">
            <select id="vac-status" name="status" class="select select-bordered select-sm" data-autosubmit>
                <option value="">{{ __('Alle Status') }}</option>
                @foreach (\App\Enums\Vacation\VacationStatus::options() as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </x-filter-field>

        <x-filter-field :label="__('Typ')" for="vac-type">
            <select id="vac-type" name="type" class="select select-bordered select-sm" data-autosubmit>
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

    <x-table table-sort="client" scroll="flex" :pinRows="true" :zebra="true" size="sm">
                <x-slot:head>
                    <tr>
                        @if ($isAdmin)
                            <x-table.th sort type="string">{{ __('Mitarbeiter') }}</x-table.th>
                        @endif
                        <x-table.th sort type="date" default="desc">{{ __('Zeitraum') }}</x-table.th>
                        <x-table.th sort type="number">{{ __('Tage') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Typ') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                        <th>{{ __('Notiz') }}</th>
                        <th class="w-px"></th>
                    </tr>
                </x-slot:head>
                    @forelse ($vacations as $v)
                        @php
                            $days = $v->workingDays($holidayService);
                        @endphp
                        <tr class="hover">
                            @if ($isAdmin)
                                <td class="font-medium">{{ $v->user?->name ?? '–' }}</td>
                            @endif
                            <td class="whitespace-nowrap" data-sort-value="{{ $v->start_date->format('Y-m-d') }}">
                                {{ $v->start_date->fdate() }}
                                @if ($v->start_date->ne($v->end_date))
                                    – {{ $v->end_date->fdate() }}
                                @endif
                            </td>
                            <td class="tabular-nums">{{ $days }}</td>
                            <td>
                                <x-status-badge tone="ghost" size="sm">{{ $v->typeLabel() }}</x-status-badge>
                            </td>
                            <td>
                                <x-status-badge size="sm" :tone="$v->statusTone()">{{ $v->statusLabel() }}</x-status-badge>
                                @if ($v->reject_reason)
                                    <span class="tooltip tooltip-right" data-tip="{{ $v->reject_reason }}">
                                        <x-icon name="info" class="text-error" />
                                    </span>
                                @endif
                            </td>
                            <td class="max-w-xs truncate text-muted text-xs">{{ $v->note }}</td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @can('decide', $v)
                                        @if ($v->status === \App\Enums\Vacation\VacationStatus::Pending)
                                            <x-action-form :action="route('vacations.approve', $v)" method="PATCH">
                                                <x-icon-btn icon="check" tone="success" type="submit" :label="__('Genehmigen')" />
                                            </x-action-form>
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
                                        <x-action-form :action="route('vacations.cancel', $v)" method="PATCH"
                                              :confirm="__('Urlaubsantrag wirklich stornieren?')"
                                              :confirm-label="__('Stornieren')">
                                            <x-icon-btn icon="block" tone="warning" type="submit" :label="__('Stornieren')" />
                                        </x-action-form>
                                    @endcan

                                    @can('delete', $v)
                                        <x-action-form :action="route('vacations.destroy', $v)" method="DELETE"
                                              :confirm="__('Urlaubsantrag wirklich löschen?')"
                                              :confirm-label="__('Löschen')">
                                            <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                        </x-action-form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon="beach_access" :colspan="$isAdmin ? 7 : 6" :title="__('Keine Einträge gefunden')" compact />
                    @endforelse
    </x-table>

    {{-- Pagination --}}
    <x-pagination :paginator="$vacations" standing />

</x-index-page>
@endsection
