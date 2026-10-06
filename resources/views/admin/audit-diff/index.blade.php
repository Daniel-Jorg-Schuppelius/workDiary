{{--
  Created on   : Thu Aug 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Stammdaten-Versionsvergleich (MVP-528): Timeline aus audit_logs mit
  A/B-Auswahl und Feld-Diff.
--}}

@extends('layouts.app')
@section('title', __('Änderungsverlauf & Versionsvergleich'))
@section('nav-title', __('Änderungsverlauf'))
@include('partials.page-fill')

@section('content')
<x-page-shell overflow="clip">
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Zwei Änderungsstände eines Datensatzes vergleichen — aus der revisionssicheren Audit-Kette, nur Anzeige.')" />
    </x-slot:toolbar>

    <x-filter-bar :action="route('admin.audit-diff.index')" :reset="route('admin.audit-diff.index')">
        <x-filter-field :label="__('Typ')" for="ad-type">
            <select id="ad-type" name="type" class="select select-sm select-bordered" data-autosubmit>
                <option value="">{{ __('— wählen —') }}</option>
                @foreach ($types as $key => $meta)
                    <option value="{{ $key }}" @selected($typeKey === $key)>{{ $meta['label'] }}</option>
                @endforeach
            </select>
        </x-filter-field>
        @if ($typeKey !== '' && $records->isNotEmpty())
            <x-filter-field :label="__('Datensatz')" for="ad-record">
                <select id="ad-record" name="record" class="select select-sm select-bordered w-64" data-autosubmit>
                    <option value="">{{ __('— wählen —') }}</option>
                    @foreach ($records as $candidate)
                        <option value="{{ \App\Support\Sqid::encode($types[$typeKey]['class'], (int) $candidate->id) }}"
                                @selected($record !== null && (int) $candidate->id === (int) $record->id)>
                            {{ $candidate->name }}
                        </option>
                    @endforeach
                </select>
            </x-filter-field>
        @endif
    </x-filter-bar>

    @if ($record !== null && $logs !== null)
        @if ($diff !== null)
            <x-card class="shrink-0">
                <h3 class="font-semibold mb-2">{{ __('Unterschiede zwischen Stand A und Stand B') }}</h3>
                @if (empty($diff))
                    <x-empty-state icon="difference" :title="__('Keine Feldänderungen zwischen den gewählten Ständen.')" compact />
                @else
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <x-table.th>{{ __('Feld') }}</x-table.th>
                                <x-table.th>{{ __('Stand A') }}</x-table.th>
                                <x-table.th>{{ __('Stand B') }}</x-table.th>
                            </tr>
                        </x-slot:head>
                        @foreach ($diff as $row)
                            <tr>
                                <td class="font-mono text-sm">{{ $row['field'] }}</td>
                                <td class="text-error/80 break-all">{{ $row['before'] }}</td>
                                <td class="text-success break-all">{{ $row['after'] }}</td>
                            </tr>
                        @endforeach
                    </x-table>
                @endif
            </x-card>
        @endif

        {{-- Die Radios der Timeline gehören über form= zu diesem Formular. Ein gewählter
             Stand einer anderen Seite reist als verstecktes Feld mit; wählt man auf dieser
             Seite neu, folgt das Radio im Dokument danach und sein Wert gewinnt. --}}
        <form method="GET" action="{{ route('admin.audit-diff.index') }}" id="audit-diff-select" class="contents">
            <input type="hidden" name="type" value="{{ $typeKey }}">
            <input type="hidden" name="record" value="{{ $recordSqid }}">
            <input type="hidden" name="page" value="{{ $logs->currentPage() }}">
            @if ($stateA !== null && ! $logs->contains('id', $stateA->id))
                <input type="hidden" name="a" value="{{ $stateA->id }}">
            @endif
            @if ($stateB !== null && ! $logs->contains('id', $stateB->id))
                <input type="hidden" name="b" value="{{ $stateB->id }}">
            @endif
        </form>

        <div class="flex shrink-0 flex-wrap items-center gap-x-4 gap-y-2">
            <h3 class="font-semibold">{{ __('Änderungs-Timeline') }} — {{ $record->name }}</h3>
            <span class="text-sm text-muted">{{ trans_choice(':count Eintrag|:count Einträge', $logs->total(), ['count' => $logs->total()]) }}</span>
            @if ($logs->total() > 0)
                @foreach ([__('Stand A') => $stateA, __('Stand B') => $stateB] as $stateLabel => $state)
                    <span class="text-sm">
                        {{ $stateLabel }}:
                        @if ($state !== null)
                            <span class="tabular-nums">{{ $state->created_at?->fdatetime() }}</span>
                            · <span class="font-mono">{{ $state->event }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </span>
                @endforeach
                <x-button type="submit" form="audit-diff-select" class="ml-auto">{{ __('Stände vergleichen') }}</x-button>
            @endif
        </div>

        <x-table scroll="flex" :pinRows="true" empty-icon="history" :empty-title="__('Keine Audit-Einträge zu diesem Datensatz.')">
            <x-slot:head>
                <tr>
                    <x-table.th>A</x-table.th>
                    <x-table.th>B</x-table.th>
                    <x-table.th>{{ __('Zeitpunkt') }}</x-table.th>
                    <x-table.th>{{ __('Ereignis') }}</x-table.th>
                    <x-table.th>{{ __('Benutzer') }}</x-table.th>
                </tr>
            </x-slot:head>
            @foreach ($logs as $log)
                <tr class="hover">
                    <td><input type="radio" name="a" value="{{ $log->id }}" form="audit-diff-select" class="radio radio-xs" data-autosubmit
                               @checked($selectedA === (int) $log->id)></td>
                    <td><input type="radio" name="b" value="{{ $log->id }}" form="audit-diff-select" class="radio radio-xs" data-autosubmit
                               @checked($selectedB === (int) $log->id)></td>
                    <td class="tabular-nums">{{ $log->created_at?->fdatetime() }}</td>
                    <td class="font-mono text-sm">{{ $log->event }}</td>
                    <td>{{ $log->user?->name ?? __('System') }}</td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$logs" standing />
    @endif
</x-page-shell>
@endsection
