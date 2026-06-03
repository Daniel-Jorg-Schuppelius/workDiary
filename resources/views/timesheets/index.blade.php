@extends('layouts.app')
@section('title', __('Stundenzettel'))
@section('nav-title', __('Stundenzettel'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Stundenzettel verwalten und signieren lassen.')">
    <x-slot:actions>
        @can('create', \App\Models\Timesheet::class)
            <x-icon-btn icon="add" tone="primary" size="sm" type="button"
                        onclick="document.getElementById('quick-timesheet-dialog').showModal()"
                        show-label>{{ __('Stundenzettel anlegen') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    {{-- Filter --}}
    <x-filter-bar :action="route('timesheets.index')" :reset="route('timesheets.index')">
        @if($isAdmin)
            <x-filter-field :label="__('Bereich')" for="ts-scope">
                <select id="ts-scope" name="scope" class="select select-sm select-bordered" onchange="this.form.submit()">
                    <option value="mine" @selected($scope==='mine')>{{ __('Eigene') }}</option>
                    <option value="team" @selected($scope==='team')>{{ __('Team') }}</option>
                </select>
            </x-filter-field>
        @endif
        <x-filter-field :label="__('Status')" for="ts-status">
            <select id="ts-status" name="status" class="select select-sm select-bordered" onchange="this.form.submit()">
                <option value="">{{ __('Alle Status') }}</option>
                @foreach(\App\Enums\Timesheet\TimesheetStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(request('status')===$s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    @if($timesheets->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">description</span>'
            :title="__('Keine Stundenzettel gefunden')"
            :message="__('Lege den ersten Stundenzettel über den Button oben rechts an.')"
        />
    @else
        <x-table scroll="flex" :pinRows="true" table-sort="server"
                 :route="route('timesheets.index')"
                 :current-sort="$sort ?? null"
                 :current-dir="$dir ?? 'desc'"
                 :sort-params="array_filter([
                     'scope' => request('scope'),
                     'project' => $selectedProjectSqid ?? null,
                     'status' => request('status'),
                 ], fn ($v) => $v !== null && $v !== '')">
            <x-slot:head>
                <tr>
                    <x-table.th sort="work_date" default>{{ __('Datum') }}</x-table.th>
                    <x-table.th sort="project_id">{{ __('Projekt') }}</x-table.th>
                    <x-table.th sort="user_id">{{ __('Mitarbeiter') }}</x-table.th>
                    <th class="text-right">{{ __('Arbeit') }}</th>
                    <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
                @foreach($timesheets as $ts)
                    <?php $h = intdiv((int)$ts->total_work_minutes, 60); ?>
                    <?php $m = (int)$ts->total_work_minutes % 60; ?>
                    <?php $tsIsSunday = $ts->work_date && \Carbon\Carbon::parse($ts->work_date)->isSunday(); ?>
                    <tr class="{{ $tsIsSunday ? 'text-error' : '' }}">
                        <td>{{ optional($ts->work_date)->format('d.m.Y') }}</td>
                        <td>{{ $ts->project?->name }}</td>
                        <td>{{ $ts->user?->name }}</td>
                        <td class="text-right tabular-nums">{{ $h }}:{{ str_pad((string)$m,2,'0',STR_PAD_LEFT) }} h</td>
                        <td><x-status-badge size="sm" :tone="$ts->statusTone()">{{ $ts->statusLabel() }}</x-status-badge></td>
                        <td class="text-right">
                            <x-icon-btn icon="open_in_new"
                                        :href="route('projects.timesheets.show', [$ts->project, $ts])"
                                        :label="__('Öffnen')" />
                        </td>
                    </tr>
                @endforeach
        </x-table>
        <x-pagination :paginator="$timesheets" />
    @endif
</x-index-page>

@can('create', \App\Models\Timesheet::class)
    <x-modal id="quick-timesheet-dialog"
             :embedded="false"
             size="lg"
             tone="primary"
             icon="timer"
             :eyebrow="__('Stundenzettel')"
             :title="__('Stundenzettel anlegen')"
             :action="route('timesheets.quick')"
             method="POST"
             :submitLabel="__('Anlegen')">

        <x-form-group :legend="__('Kunde')" icon="business" tone="primary">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Kunde') }}</label>
                <select name="customer_id" required class="select select-bordered w-full">
                    <option value="">{{ __('Kunde wählen…') }}</option>
                    @foreach (\App\Models\Customer::query()->whereNull('archived_at')->orderBy('name')->get(['id','name']) as $c)
                        <option value="{{ $c->sqid }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-base-content/60">{{ __('Ohne Projektwahl landet der Stundenzettel im Standardprojekt des Kunden (z. B. Wartung).') }}</p>
            </div>
        </x-form-group>

        <x-form-group :legend="__('Zeitraum')" icon="event" tone="info">
            <div class="fieldset">
                <label class="fieldset-label">{{ __('Datum') }}</label>
                <input type="date" name="work_date" value="{{ now()->format('Y-m-d') }}" class="input input-bordered w-full">
            </div>
        </x-form-group>
    </x-modal>
@endcan
@endsection
