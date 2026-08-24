{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Inbox Monatsfreigaben'))
@section('nav-title', __('Inbox Monatsfreigaben'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    use App\Enums\TimeApproval\MonthClosureStatus;
@endphp

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Eingereichte und entschiedene Monate der Organisation.')">
        <x-filter-bar :action="route('admin.month-approval.index')" :reset="route('admin.month-approval.index')">
            <select name="status" class="select select-sm select-bordered w-40 shrink-0">
                <option value="all" @selected($filters['status'] === 'all')>{{ __('Alle Status') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <select name="user" class="select select-sm select-bordered w-48 shrink-0">
                <option value="">{{ __('Alle Mitarbeitenden') }}</option>
                @foreach ($teamUsers as $u)
                    <option value="{{ $u->sqid }}" @selected($filters['user'] === $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
            <input type="number" name="year" min="2000" max="2999" value="{{ $filters['year'] }}"
                   class="input input-sm input-bordered w-24 shrink-0" placeholder="{{ __('Jahr') }}"
                   aria-label="{{ __('Jahr') }}" />
        </x-filter-bar>

        @if ($closures->isEmpty())
            <x-empty-state framed
                icon='<span class="material-symbols-outlined" aria-hidden="true">inbox</span>'
                :title="__('Keine Monate im Filter')"
                :message="__('Passen Sie die Filter an oder warten Sie auf eingereichte Monate.')" />
        @else
            @if (session('status'))
                <div role="alert" class="alert alert-success"><span>{{ session('status') }}</span></div>
            @endif

            <x-table scroll="flex" :pinRows="true" table-sort="server"
                     :route="route('admin.month-approval.index')" :current-sort="$sort" :current-dir="$dir"
                     :sort-params="request()->except(['sort', 'dir', 'page'])">
                <x-slot:head>
                    <tr>
                        <th>{{ __('Mitarbeitende:r') }}</th>
                        <x-table.th sort="period_year">{{ __('Periode') }}</x-table.th>
                        <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                        <x-table.th sort="days_open" align="right">{{ __('Tage offen') }}</x-table.th>
                        <x-table.th sort="warnings_count" align="right">{{ __('Warnungen') }}</x-table.th>
                        <x-table.th sort="submitted_at">{{ __('Eingereicht') }}</x-table.th>
                        <th class="text-right">{{ __('Aktionen') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($closures as $c)
                    <tr>
                        <td class="font-medium">{{ $c->user?->name }}</td>
                        <td class="tabular-nums">{{ $c->periodLabel() }}</td>
                        <td><x-status-badge :tone="$c->status->tone()" size="sm">{{ $c->status->label() }}</x-status-badge></td>
                        <td class="text-right tabular-nums">{{ $c->days_open }}</td>
                        <td class="text-right tabular-nums">{{ $c->warnings_count }}</td>
                        <td class="text-xs tabular-nums">{{ $c->submitted_at?->fdatetime() }}</td>
                        <td class="text-right">
                            <div class="flex justify-end gap-1">
                                @can('approve', $c)
                                    <form method="POST" action="{{ route('admin.month-approval.approve', $c) }}">
                                        @csrf
                                        <x-icon-btn icon="check" tone="success" size="sm" type="submit"
                                                    :aria-label="__('Freigeben')" />
                                    </form>
                                @endcan
                                @can('reject', $c)
                                    <button type="button" class="btn btn-sm btn-warning"
                                            data-open-dialog="reject-{{ $c->id }}">
                                        <span class="material-symbols-outlined text-base">close</span>
                                    </button>
                                    {{-- Gemeinsamer Dialog-Wrapper statt rohem <dialog> (Vollaudit 2026-07, N57). --}}
                                    <x-modal :id="'reject-' . $c->id" :embedded="false" tone="warning" icon="block"
                                        :title="__('Monat ablehnen')"
                                        :action="route('admin.month-approval.reject', $c)"
                                        :submit-label="__('Ablehnen')" submit-class="btn-warning">
                                        <textarea name="note" required minlength="20" maxlength="2000" rows="4"
                                                  class="textarea textarea-bordered w-full"
                                                  placeholder="{{ __('Begründung (mind. 20 Zeichen)') }}"></textarea>
                                    </x-modal>
                                @endcan
                                @can('reopen', $c)
                                    @if (in_array($c->status, [MonthClosureStatus::Approved, MonthClosureStatus::Locked], true))
                                        <button type="button" class="btn btn-sm btn-ghost"
                                                data-open-dialog="reopen-{{ $c->id }}"
                                                aria-label="{{ __('Wieder öffnen') }}">
                                            <span class="material-symbols-outlined text-base">lock_open</span>
                                        </button>
                                        {{-- Gemeinsamer Dialog-Wrapper statt rohem <dialog> (Vollaudit 2026-07, N57). --}}
                                        <x-modal :id="'reopen-' . $c->id" :embedded="false" tone="warning" icon="lock_open"
                                            :title="__('Monat wieder öffnen')"
                                            :action="route('admin.month-approval.reopen', $c)"
                                            :submit-label="__('Wieder öffnen')" submit-class="btn-warning">
                                            <textarea name="note" required minlength="20" maxlength="2000" rows="4"
                                                      class="textarea textarea-bordered w-full"
                                                      placeholder="{{ __('Begründung (mind. 20 Zeichen)') }}"></textarea>
                                        </x-modal>
                                    @endif
                                @endcan
                                @can('lock', $c)
                                    <form method="POST" action="{{ route('admin.month-approval.lock', $c) }}">
                                        @csrf
                                        <x-icon-btn icon="lock" tone="ghost" size="sm" type="submit"
                                                    :aria-label="__('Sperren')" />
                                    </form>
                                @endcan
                                @can('bundle', $c)
                                    {{-- Prüfexport-Bundle (Rang 40): ZIP mit Manifest, abgelegt am Monat. --}}
                                    <form method="POST" action="{{ route('admin.month-approval.bundle', $c) }}">
                                        @csrf
                                        <x-icon-btn icon="folder_zip" tone="ghost" size="sm" type="submit"
                                                    :aria-label="__('Prüfpaket erzeugen')" />
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>

            <x-pagination :paginator="$closures" standing />
        @endif
    </x-index-page>
@endsection
