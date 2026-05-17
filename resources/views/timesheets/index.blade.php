@extends('layouts.app')
@section('title', __('Stundenzettel'))
@section('nav-title', __('Stundenzettel'))

@section('content')
<x-page-shell>
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
                @foreach(\App\Models\Timesheet::STATUSES as $s)
                    <option value="{{ $s }}" @selected(request('status')===$s)>{{ __(\Illuminate\Support\Str::ucfirst($s)) }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-slot:extra>
            @can('create', \App\Models\Timesheet::class)
                <button type="button" class="btn btn-sm btn-primary gap-1" onclick="document.getElementById('quick-timesheet-dialog').showModal()">
                    <x-icon name="add" />
                    <span>{{ __('Stundenzettel') }}</span>
                </button>
            @endcan
        </x-slot:extra>
    </x-filter-bar>

    @if($timesheets->isEmpty())
        <x-card>
            <x-empty-state
                :title="__('Keine Stundenzettel gefunden')"
                :message="__('Lege den ersten Stundenzettel über den Button oben rechts an.')"
            />
        </x-card>
    @else
        <x-table>
            <thead>
                <tr>
                    <?php $p = request()->only('scope', 'project', 'status'); ?>
                    <th><x-sort-th column="work_date" :route="route('timesheets.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'desc'" default="work_date">{{ __('Datum') }}</x-sort-th></th>
                    <th><x-sort-th column="project_id" :route="route('timesheets.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'desc'">{{ __('Projekt') }}</x-sort-th></th>
                    <th><x-sort-th column="user_id" :route="route('timesheets.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'desc'">{{ __('Mitarbeiter') }}</x-sort-th></th>
                    <th class="text-right">{{ __('Arbeit') }}</th>
                    <th><x-sort-th column="status" :route="route('timesheets.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'desc'">{{ __('Status') }}</x-sort-th></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($timesheets as $ts)
                    <?php $h = intdiv((int)$ts->total_work_minutes, 60); ?>
                    <?php $m = (int)$ts->total_work_minutes % 60; ?>
                    <?php $tsIsSunday = $ts->work_date && \Carbon\Carbon::parse($ts->work_date)->isSunday(); ?>
                    <tr class="{{ $tsIsSunday ? 'text-error' : '' }}">
                        <td>{{ optional($ts->work_date)->format('d.m.Y') }}</td>
                        <td>{{ $ts->project?->name }}</td>
                        <td>{{ $ts->user?->name }}</td>
                        <td class="text-right tabular-nums">{{ $h }}:{{ str_pad((string)$m,2,'0',STR_PAD_LEFT) }} h</td>
                        <td><span class="badge badge-sm badge-{{ $ts->statusTone() }}">{{ $ts->statusLabel() }}</span></td>
                        <td class="text-right">
                            <a href="{{ route('projects.timesheets.show', [$ts->project, $ts]) }}" class="btn btn-xs">{{ __('Öffnen') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        <div>{{ $timesheets->links() }}</div>
    @endif
</x-page-shell>

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
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
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
