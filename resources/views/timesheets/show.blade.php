@extends('layouts.app')
@section('title', __('Stundenzettel') . ' #' . $timesheet->id)

@section('content')
@php
    $editable = $timesheet->canEdit();
    $fmtMin = fn(int $min) => intdiv($min, 60) . ':' . str_pad((string)($min % 60), 2, '0', STR_PAD_LEFT);
@endphp

<div class="flex h-full min-h-0 w-full flex-col gap-4 overflow-auto">

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-3 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">
                    {{ __('Stundenzettel') }} – {{ optional($timesheet->work_date)->format('d.m.Y') }}
                </h1>
                <span class="badge badge-{{ $timesheet->statusTone() }}">{{ $timesheet->statusLabel() }}</span>
            </div>
            <div class="mt-1 text-sm text-base-content/70">
                <a href="{{ route('projects.show', $project) }}#timesheets" class="link">{{ $project->name }}</a>
                · {{ $timesheet->user?->name }}
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            @if($editable)
                <a href="{{ route('projects.timesheets.edit', [$project, $timesheet]) }}" class="btn btn-sm btn-ghost">{{ __('Kopfdaten') }}</a>
                <form method="POST" action="{{ route('projects.timesheets.submit', [$project, $timesheet]) }}">@csrf
                    <button class="btn btn-sm btn-primary">{{ __('Einreichen') }}</button>
                </form>
                <form method="POST" action="{{ route('projects.timesheets.magic-link', [$project, $timesheet]) }}">@csrf
                    <button class="btn btn-sm btn-secondary">{{ __('Sign-Link an Kunden') }}</button>
                </form>
            @endif
            <a href="{{ route('projects.timesheets.pdf', [$project, $timesheet]) }}" target="_blank" class="btn btn-sm">PDF</a>

            @can('lock', $timesheet)
                @if(! $timesheet->isLocked())
                    <form method="POST" action="{{ route('projects.timesheets.lock', [$project, $timesheet]) }}">@csrf
                        <button class="btn btn-sm btn-warning">{{ __('Sperren') }}</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('projects.timesheets.unlock', [$project, $timesheet]) }}">@csrf
                        <button class="btn btn-sm">{{ __('Entsperren') }}</button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- Summary --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 text-center shadow-xs">
            <div class="text-2xl font-bold">{{ $fmtMin((int)$timesheet->total_work_minutes) }} h</div>
            <div class="text-xs text-base-content/60">{{ __('Arbeit') }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 text-center shadow-xs">
            <div class="text-2xl font-bold">{{ $fmtMin((int)$timesheet->total_break_minutes) }} h</div>
            <div class="text-xs text-base-content/60">{{ __('Pause') }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 text-center shadow-xs">
            <div class="text-2xl font-bold">{{ number_format((float)$timesheet->total_material_net, 2, ',', '.') }} €</div>
            <div class="text-xs text-base-content/60">{{ __('Material netto') }}</div>
        </div>
    </div>

    {{-- Zeilen --}}
    <div class="rounded-box border border-base-300 bg-base-100 shadow-xs">
        <header class="flex items-center justify-between border-b border-base-300 px-4 py-3">
            <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Zeiteinträge') }}</span>
        </header>

        @if($editable)
            <form method="POST" action="{{ route('projects.timesheets.entries.store', [$project, $timesheet]) }}"
                  class="flex flex-wrap items-end gap-2 border-b border-base-300 px-4 py-3">
                @csrf
                <div>
                    <label class="label-text text-xs">{{ __('Start') }}</label>
                    <input type="datetime-local" name="started_at" class="input input-sm input-bordered" required>
                </div>
                <div>
                    <label class="label-text text-xs">{{ __('Ende') }}</label>
                    <input type="datetime-local" name="ended_at" class="input input-sm input-bordered" required>
                </div>
                <div>
                    <label class="label-text text-xs">{{ __('Pause min') }}</label>
                    <input type="number" name="break_minutes" value="0" min="0" max="480" class="input input-sm input-bordered w-24">
                </div>
                <div>
                    <label class="label-text text-xs">{{ __('Art') }}</label>
                    <select name="kind" class="select select-sm select-bordered">
                        <option value="work">{{ __('Arbeit') }}</option>
                        <option value="travel">{{ __('Anfahrt') }}</option>
                        <option value="standby">{{ __('Bereitschaft') }}</option>
                    </select>
                </div>
                <div>
                    <label class="label-text text-xs">{{ __('Aufgabe') }}</label>
                    <select name="task_id" class="select select-sm select-bordered">
                        <option value="">—</option>
                        @foreach($tasks as $t)
                            <option value="{{ $t->id }}">{{ $t->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-[180px]">
                    <label class="label-text text-xs">{{ __('Beschreibung') }}</label>
                    <input type="text" name="description" class="input input-sm input-bordered w-full">
                </div>
                <button class="btn btn-sm btn-primary">+ {{ __('Zeile') }}</button>
            </form>
        @endif

        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Start') }}</th>
                        <th>{{ __('Ende') }}</th>
                        <th class="text-right">{{ __('Pause') }}</th>
                        <th class="text-right">{{ __('Dauer') }}</th>
                        <th>{{ __('Art') }}</th>
                        <th>{{ __('Aufgabe') }}</th>
                        <th>{{ __('Beschreibung') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($timesheet->entries as $e)
                        <tr>
                            <td class="tabular-nums">{{ optional($e->started_at)->format('H:i') }}</td>
                            <td class="tabular-nums">{{ optional($e->ended_at)->format('H:i') }}</td>
                            <td class="text-right tabular-nums">{{ (int)$e->break_minutes }}</td>
                            <td class="text-right tabular-nums">{{ $fmtMin((int)$e->minutes) }}</td>
                            <td>{{ $e->kind }}</td>
                            <td>{{ $e->task?->title }}</td>
                            <td>{{ $e->description }}</td>
                            <td class="text-right">
                                @if($editable)
                                    <form method="POST" action="{{ route('projects.timesheets.entries.destroy', [$project, $timesheet, $e]) }}"
                                          onsubmit="return confirm('{{ __('Löschen?') }}')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-xs btn-ghost text-error">×</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-3 text-sm text-base-content/60">—</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Materialien --}}
    <div class="rounded-box border border-base-300 bg-base-100 shadow-xs">
        <header class="flex items-center justify-between border-b border-base-300 px-4 py-3">
            <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Verbrauchsmaterial') }}</span>
        </header>

        @if($editable)
            <form method="POST" action="{{ route('projects.timesheets.materials.store', [$project, $timesheet]) }}"
                  class="flex flex-wrap items-end gap-2 border-b border-base-300 px-4 py-3">
                @csrf
                <div>
                    <label class="label-text text-xs">{{ __('Aus Stamm') }}</label>
                    <select name="material_id" class="select select-sm select-bordered">
                        <option value="">— {{ __('frei') }} —</option>
                        @foreach($materials as $m)
                            <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->unit }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-[160px]">
                    <label class="label-text text-xs">{{ __('Bezeichnung') }}</label>
                    <input type="text" name="description" class="input input-sm input-bordered w-full" required>
                </div>
                <div>
                    <label class="label-text text-xs">{{ __('Menge') }}</label>
                    <input type="number" step="0.001" min="0.001" name="quantity" value="1" class="input input-sm input-bordered w-24" required>
                </div>
                <div>
                    <label class="label-text text-xs">{{ __('Einheit') }}</label>
                    <input type="text" name="unit" value="Stk." class="input input-sm input-bordered w-20" required>
                </div>
                <div>
                    <label class="label-text text-xs">{{ __('EP netto') }}</label>
                    <input type="number" step="0.0001" min="0" name="unit_price" class="input input-sm input-bordered w-28">
                </div>
                <div>
                    <label class="label-text text-xs">{{ __('USt %') }}</label>
                    <input type="number" step="0.01" min="0" max="100" name="tax_rate" class="input input-sm input-bordered w-20">
                </div>
                <button class="btn btn-sm btn-primary">+ {{ __('Material') }}</button>
            </form>
        @endif

        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Bezeichnung') }}</th>
                        <th class="text-right">{{ __('Menge') }}</th>
                        <th>{{ __('Einheit') }}</th>
                        <th class="text-right">{{ __('EP netto') }}</th>
                        <th class="text-right">{{ __('Summe netto') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($timesheet->materialUsages as $u)
                        <tr>
                            <td>{{ $u->description }}</td>
                            <td class="text-right tabular-nums">{{ rtrim(rtrim(number_format((float)$u->quantity, 3, ',', '.'), '0'), ',') }}</td>
                            <td>{{ $u->unit }}</td>
                            <td class="text-right tabular-nums">{{ $u->unit_price !== null ? number_format((float)$u->unit_price, 4, ',', '.').' €' : '—' }}</td>
                            <td class="text-right tabular-nums">{{ number_format((float)$u->line_total_net, 2, ',', '.') }} €</td>
                            <td class="text-right">
                                @if($editable)
                                    <form method="POST" action="{{ route('projects.timesheets.materials.destroy', [$project, $timesheet, $u]) }}"
                                          onsubmit="return confirm('{{ __('Löschen?') }}')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-xs btn-ghost text-error">×</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-3 text-sm text-base-content/60">—</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Signatur --}}
    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Kundenfreigabe') }}</h2>

        @if($timesheet->isSigned() || $timesheet->isLocked())
            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="text-sm">
                    <div><strong>{{ $timesheet->customer_name }}</strong> @if($timesheet->customer_role) ({{ $timesheet->customer_role }}) @endif</div>
                    @if($timesheet->customer_email)<div>{{ $timesheet->customer_email }}</div>@endif
                    <div class="mt-1 text-base-content/60">
                        {{ __('Signiert am') }}: {{ optional($timesheet->signed_at)->format('d.m.Y H:i') }}
                        @if($timesheet->signed_ip) · IP {{ $timesheet->signed_ip }} @endif
                    </div>
                    <div class="text-xs break-all text-base-content/50">SHA-256: {{ $timesheet->signature_hash }}</div>
                </div>
                @if($timesheet->signatureAttachment)
                    <div>
                        <img src="{{ route('attachments.show', $timesheet->signatureAttachment) }}"
                             alt="signature" class="max-h-40 rounded border border-base-300 bg-white p-2">
                    </div>
                @endif
            </div>
        @elseif($editable)
            @include('timesheets._signature_pad', [
                'action'        => route('projects.timesheets.sign', [$project, $timesheet]),
                'timesheet'     => $timesheet,
            ])
        @endif
    </div>
</div>
@endsection
