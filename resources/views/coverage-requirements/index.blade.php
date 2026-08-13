@extends('layouts.app')
@section('title', __('Soll-Besetzung') . ' – ' . $dutyPlan->title)
@section('nav-title', __('Soll-Besetzung'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="$dutyPlan->title . ' · ' . $dutyPlan->from_date->fdate() . ' – ' . $dutyPlan->to_date->fdate()">
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

    @if ($requirements->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">shield_person</span>'
            :title="__('Noch keine Soll-Besetzungen für diesen Dienstplan definiert.')"
            :message="__('Hinweis: Ohne Anforderungen gilt die Mindestbesetzung des Dienstplans:') . ' ' . $dutyPlan->min_staff" />
    @else
        <x-table scroll="flex" :pinRows="true" table-sort="client">
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
                $qualNames = \App\Models\Qualification::query()->pluck('name', 'id');
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
                                {{ $req->specific_date->fdate() }}
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
                            @endif
                            {{-- MVP-530: zählbare Minima („≥2 Examiniert") --}}
                            @foreach ($req->qualificationMinima() as $qid => $needed)
                                <span class="badge badge-sm badge-ghost whitespace-nowrap"
                                      title="{{ __('Mindestens :n Personen mit dieser Qualifikation', ['n' => $needed]) }}">
                                    ≥{{ $needed }} {{ $qualNames[$qid] ?? ('#' . $qid) }}
                                </span>
                            @endforeach
                            @if (empty($req->required_qualification_ids) && $req->qualificationMinima() === [])
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
                                    <x-action-form :action="route('duty-plans.coverage.destroy', [$dutyPlan, $req])" method="DELETE"
                                          :confirm="__('Anforderung wirklich löschen?')"
                                          :confirm-label="__('Löschen')">
                                        <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                    </x-action-form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
        </x-table>
    @endif
</x-index-page>
@endsection
