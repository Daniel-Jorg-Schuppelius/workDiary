@extends('layouts.app')
@section('title', __('Soll-Besetzung') . ' – ' . $dutyPlan->title)
@section('nav-title', __('Soll-Besetzung'))
@section('content')
<x-page-shell gap="6">
    <x-slot:toolbar>
        <x-page-toolbar :title="__('Soll-Besetzung')" :subtitle="$dutyPlan->title . ' · ' . $dutyPlan->from_date->format('d.m.Y') . ' – ' . $dutyPlan->to_date->format('d.m.Y')">
            <x-slot:actions>
                @can('create', \App\Models\CoverageRequirement::class)
                    <a href="{{ route('duty-plans.coverage.create', $dutyPlan) }}" data-entry-modal-trigger class="btn btn-primary btn-sm">
                        + {{ __('Anforderung hinzufügen') }}
                    </a>
                @endcan
                <a href="{{ route('duty-plans.show', $dutyPlan) }}" class="btn btn-ghost btn-sm">← {{ __('Zurück') }}</a>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($requirements->isEmpty())
        <x-card>
            <x-empty-state
                icon='<span class="material-symbols-outlined" aria-hidden="true">shield_person</span>'
                :title="__('Noch keine Soll-Besetzungen für diesen Dienstplan definiert.')"
                :message="__('Hinweis: Ohne Anforderungen gilt die Mindestbesetzung des Dienstplans:') . ' ' . $dutyPlan->min_staff" />
        </x-card>
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
                                <span class="badge badge-sm badge-warning">{{ __('Konkretes Datum') }}</span>
                                {{ $req->specific_date->format('d.m.Y') }}
                            @elseif ($req->weekday !== null)
                                <span class="badge badge-sm badge-info">{{ __('Wochentag') }}</span>
                                {{ $weekdayLabels[$req->weekday] ?? $req->weekday }}
                            @else
                                <span class="badge badge-sm badge-ghost">{{ __('Immer') }}</span>
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
                            <div class="flex justify-end gap-2">
                                @can('update', $req)
                                    <a href="{{ route('duty-plans.coverage.edit', [$dutyPlan, $req]) }}" data-entry-modal-trigger class="btn btn-ghost btn-xs">{{ __('Bearbeiten') }}</a>
                                @endcan
                                @can('delete', $req)
                                    <form method="POST" action="{{ route('duty-plans.coverage.destroy', [$dutyPlan, $req]) }}"
                                          data-confirm-dialog
                                          data-confirm-message="{{ __('Anforderung wirklich löschen?') }}"
                                          data-confirm-label="{{ __('Löschen') }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-ghost btn-xs text-error">{{ __('Löschen') }}</button>
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
