@extends('layouts.app')
@section('title', __('Stundenzettel'))
@section('nav-title', __('Stundenzettel'))

@section('content')
<div class="flex h-full min-h-0 w-full flex-col gap-4 overflow-auto">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="font-['Space_Grotesk'] text-xl font-semibold">{{ __('Stundenzettel') }}</h1>

        <form method="GET" class="flex flex-wrap items-center gap-2">
            @if($isAdmin)
                <select name="scope" class="select select-sm select-bordered" onchange="this.form.submit()">
                    <option value="mine" @selected($scope==='mine')>{{ __('Eigene') }}</option>
                    <option value="team" @selected($scope==='team')>{{ __('Team') }}</option>
                </select>
            @endif
            <select name="status" class="select select-sm select-bordered" onchange="this.form.submit()">
                <option value="">{{ __('Alle Status') }}</option>
                @foreach(\App\Models\Timesheet::STATUSES as $s)
                    <option value="{{ $s }}" @selected(request('status')===$s)>{{ __(\Illuminate\Support\Str::ucfirst($s)) }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 shadow-xs">
        @if($timesheets->isEmpty())
            <div class="px-4 py-6 text-sm text-base-content/60">{{ __('Keine Stundenzettel gefunden.') }}</div>
        @else
            <div class="overflow-x-auto">
                <table class="table table-sm">
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
                            <tr>
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
                </table>
            </div>
            <div class="border-t border-base-300 px-4 py-3">{{ $timesheets->links() }}</div>
        @endif
    </div>
</div>
@endsection
