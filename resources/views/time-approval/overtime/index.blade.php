{{--
  Created on   : Wed Aug 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Meine Überstunden-Anträge'))
@section('nav-title', __('Meine Überstunden-Anträge'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Mehrarbeit über die Rahmenzeit hinaus beantragen und genehmigen lassen.')">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('overtime.create')"
                        show-label>{{ __('Antrag stellen') }}</x-icon-btn>
        </x-slot:actions>

        <x-filter-bar :action="route('overtime.index')" :reset="route('overtime.index')">
            <select name="status" class="select select-sm select-bordered w-40 shrink-0" data-autosubmit>
                <option value="">{{ __('Alle Status') }}</option>
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
                :title="__('Keine Überstunden-Anträge')"
                :message="__('Stellen Sie einen Antrag, um Mehrarbeit genehmigen zu lassen.')" />
        @else
            <x-table scroll="flex" :pinRows="true">
                <x-slot:head>
                    <tr>
                        <th>{{ __('Bezug') }}</th>
                        <th>{{ __('Mitarbeiter:in') }}</th>
                        <th class="text-right">{{ __('Minuten') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Entscheidung') }}</th>
                        <th class="text-right">{{ __('Aktion') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($requests as $r)
                    <tr>
                        <td class="font-medium">{{ optional($r->scope_date)->fdate() }}</td>
                        <td>{{ $r->user?->name }}</td>
                        <td class="text-right tabular-nums">{{ $r->minutes }}</td>
                        <td>
                            <x-status-badge :tone="$r->status->tone()" size="sm">{{ $r->status->label() }}</x-status-badge>
                        </td>
                        <td class="text-sm text-base-content/70">
                            @if ($r->decided_at)
                                {{ $r->decidedBy?->name ?? '—' }} · {{ $r->decided_at->fdate() }}
                                @if ($r->decision_note)
                                    <span class="italic">„{{ \Illuminate\Support\Str::limit($r->decision_note, 60) }}"</span>
                                @endif
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="flex items-center gap-1 justify-end">
                                @can('withdraw', $r)
                                    <form method="POST" action="{{ route('overtime.withdraw', $r) }}">
                                        @csrf
                                        <x-icon-btn icon="undo" size="sm" tone="ghost" type="submit"
                                                    :aria-label="__('Zurückziehen')" />
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <x-pagination :paginator="$requests" standing />
        @endif
    </x-index-page>
@endsection
