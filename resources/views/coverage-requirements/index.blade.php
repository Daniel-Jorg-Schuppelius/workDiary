@extends('layouts.app')
@section('title', __('Soll-Besetzung') . ' – ' . $dutyPlan->title)
@section('nav-title', __('Soll-Besetzung'))
@section('content')
<div class="flex h-[calc(100dvh-11rem)] flex-col gap-6 overflow-auto">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <div>
            <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('Soll-Besetzung') }}</h1>
            <p class="text-sm text-base-content/70">
                {{ $dutyPlan->title }} · {{ $dutyPlan->from_date->format('d.m.Y') }} – {{ $dutyPlan->to_date->format('d.m.Y') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            @can('create', \App\Models\CoverageRequirement::class)
                <a href="{{ route('duty-plans.coverage.create', $dutyPlan) }}" data-entry-modal-trigger class="btn btn-primary btn-sm">
                    + {{ __('Anforderung hinzufügen') }}
                </a>
            @endcan
            <a href="{{ route('duty-plans.show', $dutyPlan) }}" class="btn btn-ghost btn-sm">← {{ __('Zurück') }}</a>
        </div>
    </div>

    @if ($requirements->isEmpty())
        <div class="rounded-box border border-base-300 bg-base-100 p-8 text-center text-base-content/60">
            {{ __('Noch keine Soll-Besetzungen für diesen Dienstplan definiert.') }}
            <br>
            <span class="text-sm">{{ __('Hinweis: Ohne Anforderungen gilt die Mindestbesetzung des Dienstplans:') }} <strong>{{ $dutyPlan->min_staff }}</strong></span>
        </div>
    @else
        <x-table>
            <thead>
                <tr>
                    <th>{{ __('Schichttyp') }}</th>
                    <th>{{ __('Geltungsbereich') }}</th>
                    <th class="text-center">{{ __('Min') }}</th>
                    <th class="text-center">{{ __('Max') }}</th>
                    <th>{{ __('Qualifikationen') }}</th>
                    <th>{{ __('Notizen') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
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
            </tbody>
        </x-table>
    @endif
</div>
@endsection
