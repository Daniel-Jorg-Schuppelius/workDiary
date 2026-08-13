{{--
  Created on   : Wed Aug 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Überstunden-Anträge'))
@section('nav-title', __('Überstunden-Anträge'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Beantragte Mehrarbeit entscheiden — Genehmigung quittiert den zugehörigen Rahmenzeit-Befund.')">
        <x-filter-bar :action="route('admin.overtime.index')" :reset="route('admin.overtime.index')">
            <select name="status" class="select select-sm select-bordered w-40 shrink-0" data-autosubmit>
                <option value="all" @selected(($filters['status'] ?? '') === 'all')>{{ __('Alle Status') }}</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>
                        {{ $s->label() }}
                    </option>
                @endforeach
            </select>
        </x-filter-bar>

        @if ($requests->isEmpty())
            <x-empty-state framed
                icon='<span class="material-symbols-outlined" aria-hidden="true">more_time</span>'
                :title="__('Keine Überstunden-Anträge')" />
        @else
            <x-table scroll="flex" :pinRows="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('Bezug') }}</th>
                        <th>{{ __('Mitarbeiter:in') }}</th>
                        <th class="text-right">{{ __('Minuten') }}</th>
                        <th>{{ __('Begründung') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-right">{{ __('Aktion') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($requests as $r)
                    <tr>
                        <td class="font-medium">{{ optional($r->scope_date)->fdate() }}</td>
                        <td>{{ $r->user?->name }}</td>
                        <td class="text-right tabular-nums">{{ $r->minutes }}</td>
                        <td class="text-sm text-base-content/70 max-w-md">
                            {{ \Illuminate\Support\Str::limit($r->reason, 120) }}
                        </td>
                        <td>
                            <x-status-badge :tone="$r->status->tone()" size="sm">{{ $r->status->label() }}</x-status-badge>
                            @if ($r->decided_at)
                                <div class="text-xs text-base-content/60 mt-0.5">
                                    {{ $r->decidedBy?->name ?? '—' }} · {{ $r->decided_at->fdate() }}
                                </div>
                            @endif
                        </td>
                        <td class="text-right">
                            @can('decide', $r)
                                <form method="POST" action="{{ route('admin.overtime.approve', $r) }}"
                                      class="flex items-center gap-1 justify-end flex-wrap"
                                      data-reject-action="{{ route('admin.overtime.reject', $r) }}">
                                    @csrf
                                    <input type="text" name="note" maxlength="2000"
                                           class="input input-xs input-bordered w-40"
                                           placeholder="{{ __('Anmerkung (optional)') }}">
                                    <button class="btn btn-xs btn-success" type="submit">
                                        {{ __('Genehmigen') }}
                                    </button>
                                    <button class="btn btn-xs btn-error" type="submit"
                                            formaction="{{ route('admin.overtime.reject', $r) }}">
                                        {{ __('Ablehnen') }}
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <x-pagination :paginator="$requests" standing />
        @endif
    </x-index-page>
@endsection
