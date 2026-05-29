@extends('layouts.app')
@section('title', __('Soll-Besetzung') . ' – ' . $dutyPlan->title)
@section('nav-title', __('Soll-Besetzung'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$dutyPlan->title . ' · ' . $dutyPlan->from_date->format('d.m.Y') . ' – ' . $dutyPlan->to_date->format('d.m.Y')">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm"
                            :href="route('duty-plans.show', $dutyPlan)"
                            show-label>{{ __('Zurück') }}</x-icon-btn>
                @can('create', \App\Models\CoverageRequirement::class)
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('duty-plans.coverage.create', $dutyPlan)"
                                show-label>{{ __('Anforderung hinzufügen') }}</x-icon-btn>
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($requirements->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">shield_person</span>'
            :title="__('Noch keine Soll-Besetzungen für diesen Dienstplan definiert.')"
            :message="__('Hinweis: Ohne Anforderungen gilt die Mindestbesetzung des Dienstplans:') . ' ' . $dutyPlan->min_staff" />
    @else
        <x-table table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('Schichttyp') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Geltungsbereich') }}</x-table.th>
                    <x-table.th sort type="number" align="center">{{ __('Min') }}</x-table.th>
                    <x-table.th sort type="number" align="center">{{ __('Max') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Qualifikationen') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Notizen') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @php
                $weekdayLabels = [
                    0 => __('So'), 1 => __('Mo'), 2 => __('Di'), 3 => __('Mi'),
                    4 => __('Do'), 5 => __('Fr'), 6 => __('Sa'),
                ];
            @endphp
            @foreach ($requirements as $req)
                    <tr>
                        <td>
                            @if ($req->shiftType)
                                <span class="badge badge-sm" style="background-color:{{ $req->shiftType->color }};color:#fff;">
                                    {{ $req->shiftType->abbreviation }}
                                </span>
                                {{ $req->shiftType->name }}
                            @else
                                <span class="text-base-content/40">–</span>
                            @endif
                        </td>
                        <td class="text-sm">
                            @if ($req->specific_date)
                                <x-status-badge tone="warning">{{ __('Konkretes Datum') }}</x-status-badge>
                                {{ $req->specific_date->format('d.m.Y') }}
                            @elseif ($req->weekday !== null)
                                <x-status-badge tone="info">{{ __('Wochentag') }}</x-status-badge>
                                {{ $weekdayLabels[$req->weekday] ?? $req->weekday }}
                            @else
                                <x-status-badge tone="ghost">{{ __('Immer') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-center font-semibold">{{ $req->min_staff }}</td>
                        <td class="text-center">{{ $req->max_staff ?? '∞' }}</td>
                        <td class="text-sm">
                            @if (!empty($req->required_qualification_ids))
                                {{ count($req->required_qualification_ids) }} {{ __('Qual.') }}
                            @else
                                <span class="text-base-content/30">–</span>
                            @endif
                        </td>
                        <td class="text-sm text-base-content/70">{{ Str::limit((string) $req->notes, 40) }}</td>
                        <td class="text-right">
                            <div class="flex justify-end gap-1">
                                @can('update', $req)
                                    <x-icon-btn icon="edit"
                                                data-entry-modal-trigger
                                                :href="route('duty-plans.coverage.edit', [$dutyPlan, $req])"
                                                :label="__('Bearbeiten')" />
                                @endcan
                                @can('delete', $req)
                                    <form method="POST" action="{{ route('duty-plans.coverage.destroy', [$dutyPlan, $req]) }}" class="inline"
                                          data-confirm-dialog
                                          data-confirm-message="{{ __('Anforderung wirklich löschen?') }}"
                                          data-confirm-label="{{ __('Löschen') }}">
                                        @csrf @method('DELETE')
                                        <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
        </x-table>
    @endif
</x-page-shell>
@endsection
