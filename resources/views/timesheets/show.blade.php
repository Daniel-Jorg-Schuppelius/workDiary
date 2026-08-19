{{--
  Created on   : Thu May 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Stundenzettel') . ' #' . $timesheet->id)
@section('nav-title', __('Stundenzettel') . ' #' . $timesheet->id)

@section('content')
@php
    $editable = $timesheet->canEdit();
    $canSign = auth()->user()?->can('sign', $timesheet) ?? false;
    $fmtMin = fn (int $min): string => \App\Support\Formats::duration($min, 'clock', withUnit: false);
    $entryKindLabel = static function (?\App\Enums\TimeEntry\TimeEntryKind $kind): string {
        return $kind?->label() ?? '';
    };
@endphp

<x-page-shell>

    <x-slot:toolbar>
        <x-page-toolbar :title="optional($timesheet->work_date)->fdate()"
                        :badge="$timesheet->statusLabel()"
                        :badge-tone="$timesheet->statusTone()">
            <div class="text-sm text-base-content/70">
                <a href="{{ route('projects.show', $project) }}#timesheets" class="link">{{ $project->name }}</a>
                · {{ $timesheet->user?->name }}
            </div>
            <x-slot:actions>
                <x-icon-btn icon="picture_as_pdf" size="sm"
                            :href="route('projects.timesheets.pdf', [$project, $timesheet])"
                            target="_blank"
                            show-label>{{ __('PDF') }}</x-icon-btn>
                @if($editable)
                    <x-icon-btn icon="edit" size="sm"
                                data-entry-modal-trigger
                                :href="route('projects.timesheets.edit', [$project, $timesheet])"
                                show-label>{{ __('Kopfdaten') }}</x-icon-btn>
                    <x-action-form :action="route('projects.timesheets.magic-link', [$project, $timesheet])">
                        <x-icon-btn icon="link" tone="secondary" size="sm" type="submit" show-label>{{ __('Sign-Link an Kunden') }}</x-icon-btn>
                    </x-action-form>
                    {{-- Magic-Link widerrufen (Feature 012 MVP; Vollaudit 2026-07, M6). --}}
                    @if ($timesheet->magic_token !== null)
                        <x-action-form method="DELETE" :action="route('projects.timesheets.magic-link.revoke', [$project, $timesheet])">
                            <x-icon-btn icon="link_off" tone="ghost" size="sm" type="submit" show-label>{{ __('Sign-Link widerrufen') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                    <x-action-form :action="route('projects.timesheets.submit', [$project, $timesheet])">
                        <x-icon-btn icon="send" tone="primary" size="sm" type="submit" show-label>{{ __('Einreichen') }}</x-icon-btn>
                    </x-action-form>
                @endif
                @can('lock', $timesheet)
                    @if(! $timesheet->isLocked())
                        <x-action-form :action="route('projects.timesheets.lock', [$project, $timesheet])">
                            <x-icon-btn icon="lock" tone="warning" size="sm" type="submit" show-label>{{ __('Sperren') }}</x-icon-btn>
                        </x-action-form>
                    @else
                        <x-action-form :action="route('projects.timesheets.unlock', [$project, $timesheet])">
                            <x-icon-btn icon="lock_open" size="sm" type="submit" show-label>{{ __('Entsperren') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    {{-- Stoppuhr direkt am Zettel: laufende Zeit landet über den Stopwatch-Service
         sofort als Zeile in genau diesem Stundenzettel. Vorher gab es Start/Stopp
         nur auf der Tagesansicht, obwohl der Service dafür gebaut ist. --}}
    @if ($editable)
        <x-card as="section">
            @php $runsHere = $runningEntry && (int) $runningEntry->timesheet_id === (int) $timesheet->id; @endphp

            @if ($runsHere)
                <div class="flex flex-wrap items-center gap-3"
                     x-data="stopwatch('{{ $runningEntry->started_at?->toIso8601String() }}')">
                    <span class="relative flex size-2.5">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75"></span>
                        <span class="relative inline-flex size-2.5 rounded-full bg-primary"></span>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-['Space_Grotesk'] text-sm font-semibold">{{ __('Läuft…') }}</p>
                        @if ($runningEntry->description)
                            <p class="truncate text-xs text-base-content/60">{{ $runningEntry->description }}</p>
                        @endif
                    </div>
                    <span class="font-['Space_Grotesk'] text-2xl font-semibold tabular-nums text-primary"
                          x-text="display">00:00:00</span>
                    <form method="POST" action="{{ route('stopwatch.stop') }}" class="leading-none">
                        @csrf
                        <x-button type="submit" tone="error" size="sm" icon="stop_circle">{{ __('Stoppen') }}</x-button>
                    </form>
                </div>
            @elseif ($runningEntry)
                {{-- Auf einem anderen Zettel/Projekt läuft schon etwas — Stopwatch::start
                     würde hier ohnehin abbrechen, also gleich den Ausweg anbieten. --}}
                <div class="flex flex-wrap items-center gap-3">
                    <x-icon name="timer" class="text-warning" />
                    <p class="min-w-0 flex-1 text-sm text-base-content/70">
                        {{ __('Es läuft bereits eine Zeiterfassung:') }}
                        <span class="font-medium">{{ $runningEntry->project?->name ?: $runningEntry->description }}</span>
                    </p>
                    <form method="POST" action="{{ route('stopwatch.stop') }}" class="leading-none">
                        @csrf
                        <x-button type="submit" tone="error" size="sm" icon="stop_circle">{{ __('Stoppen') }}</x-button>
                    </form>
                </div>
            @else
                <form method="POST" action="{{ route('stopwatch.start') }}"
                      class="flex flex-wrap items-center gap-2">
                    @csrf
                    <input type="hidden" name="project_id" value="{{ $project->sqid }}">
                    <input type="hidden" name="timesheet_id" value="{{ $timesheet->sqid }}">
                    <input type="text" name="description" maxlength="500"
                           class="input input-bordered input-sm min-w-40 flex-1"
                           placeholder="{{ __('Woran arbeitest du?') }}">
                    @if ($tasks->isNotEmpty())
                        <select name="task_id" class="select select-bordered select-sm w-full sm:w-56">
                            <option value="">{{ __('Keine Aufgabe') }}</option>
                            @foreach ($tasks as $t)
                                <option value="{{ $t->sqid }}">{{ $t->title }}</option>
                            @endforeach
                        </select>
                    @endif
                    <x-button type="submit" tone="primary" size="sm" icon="play_circle">{{ __('Starten') }}</x-button>
                </form>
            @endif
        </x-card>
    @endif

    {{-- Direkt nach dem Anlegen (`?add=entry`): Zeilendialog ohne Extraklick
         öffnen, damit der Flow „Stundenzettel anlegen → eintragen, was gemacht
         wurde" nicht abreißt. Das JS entfernt den Parameter danach aus der
         Adresszeile. --}}
    @if ($editable && request()->query('add') === 'entry')
        {{-- Liegen für den Tag schon erfasste Zeiten herum, ist deren Übernahme
             der nächste Schritt — nicht ein leeres Formular. --}}
        <a class="hidden" aria-hidden="true" tabindex="-1"
           data-entry-modal-autoopen="add"
           href="{{ $adoptableCount > 0
                ? route('projects.timesheets.entries.adopt.form', [$project, $timesheet])
                : route('projects.timesheets.entries.create', [$project, $timesheet]) }}"></a>
    @endif

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-kpi-tile :label="__('Arbeit')"         :value="$fmtMin((int)$timesheet->total_work_minutes) . ' h'" />
        <x-kpi-tile :label="__('Pause')"          :value="$fmtMin((int)$timesheet->total_break_minutes) . ' h'" />
        <x-kpi-tile :label="__('Material netto')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float)$timesheet->total_material_net, 2, withThousandsSeparator: true) . ' €'" />
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 shadow-xs">
        <header class="flex items-center justify-between border-b border-base-300 px-4 py-3">
            <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Zeiteinträge') }}</span>
            @if($editable)
                <span class="flex flex-wrap items-center gap-2">
                    {{-- Erfasste Zeiten dieses Tages anhängen statt abtippen. --}}
                    @if ($adoptableCount > 0)
                        <x-icon-btn icon="playlist_add_check" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('projects.timesheets.entries.adopt.form', [$project, $timesheet])"
                                    show-label>{{ __('Zeiten übernehmen') }} ({{ $adoptableCount }})</x-icon-btn>
                    @endif
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('projects.timesheets.entries.create', [$project, $timesheet])"
                                show-label>{{ __('Zeile hinzufügen') }}</x-icon-btn>
                </span>
            @endif
        </header>

        <x-table table-sort="client" bare>
            <x-slot:head>
                <tr>
                    <x-table.th sort>{{ __('Start') }}</x-table.th>
                    <x-table.th sort>{{ __('Ende') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Pause') }}</x-table.th>
                    <x-table.th sort type="duration" align="right">{{ __('Dauer') }}</x-table.th>
                    <x-table.th sort>{{ __('Art') }}</x-table.th>
                    <x-table.th sort>{{ __('Aufgabe') }}</x-table.th>
                    <x-table.th sort>{{ __('Beschreibung') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse($timesheet->entries as $e)
                <tr>
                    <td class="tabular-nums">{{ $e->started_at?->ftime() }}</td>
                    <td class="tabular-nums">{{ $e->ended_at?->ftime() }}</td>
                    <td class="text-right tabular-nums">{{ (int)$e->break_minutes }}</td>
                    <td class="text-right tabular-nums" data-sort-value="{{ (int) $e->minutes }}">{{ $fmtMin((int)$e->minutes) }}</td>
                    <td>{{ $entryKindLabel($e->kind) }}</td>
                    <td>{{ $e->task?->title }}</td>
                    <td>
                        {{ $e->description }}
                        @if ($e->tags->isNotEmpty())
                            <span class="mt-0.5 flex flex-wrap gap-1">
                                @foreach ($e->tags as $tag)
                                    <span class="badge badge-xs" style="background:{{ $tag->color ?? '#94a3b8' }};color:#fff">{{ $tag->name }}</span>
                                @endforeach
                            </span>
                        @endif
                    </td>
                    <td class="text-right">
                        @if($editable)
                            <x-action-form :action="route('projects.timesheets.entries.destroy', [$project, $timesheet, $e])" method="DELETE"
                                  :confirm="__('Löschen?')"
                                  confirm-icon="delete"
                                  confirm-tone="error"
                                  :confirm-label="__('Löschen')">
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </x-action-form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :icon="false" :colspan="8" compact />
            @endforelse
        </x-table>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 shadow-xs">
        <header class="flex items-center justify-between border-b border-base-300 px-4 py-3">
            <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Verbrauchsmaterial') }}</span>
            @if($editable)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('projects.timesheets.materials.create', [$project, $timesheet])"
                            show-label>{{ __('Material erfassen') }}</x-icon-btn>
            @endif
        </header>

        <x-table table-sort="client" bare>
            <x-slot:head>
                <tr>
                    <x-table.th sort>{{ __('Bezeichnung') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Menge') }}</x-table.th>
                    <x-table.th sort>{{ __('Einheit') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('EP netto') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Summe netto') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse($timesheet->materialUsages as $u)
                <tr>
                    <td>{{ $u->description }}</td>
                    <td class="text-right tabular-nums" data-sort-value="{{ ($u->quantity?->getValue()->toFloat() ?? 0.0) }}">{{ rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($u->quantity?->getValue()->toFloat() ?? 0.0), 3, withThousandsSeparator: true), '0'), ',') }}</td>
                    <td>{{ $u->unit }}</td>
                    <td class="text-right tabular-nums" data-sort-value="{{ ($u->unit_price?->toFloat() ?? 0.0) }}">{{ $u->unit_price !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($u->unit_price?->toFloat() ?? 0.0), 4, withThousandsSeparator: true).' €' : '—' }}</td>
                    <td class="text-right tabular-nums" data-sort-value="{{ ($u->line_total_net?->toFloat() ?? 0.0) }}">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($u->line_total_net?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }} €</td>
                    <td class="text-right">
                        @if($editable)
                            <x-action-form :action="route('projects.timesheets.materials.destroy', [$project, $timesheet, $u])" method="DELETE"
                                  :confirm="__('Löschen?')"
                                  confirm-icon="delete"
                                  confirm-tone="error"
                                  :confirm-label="__('Löschen')">
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </x-action-form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty :icon="false" :colspan="6" compact />
            @endforelse
        </x-table>
    </div>

    {{-- Signatur — kompakte Karte unterhalb des Stundenzettels --}}
    <x-card :title="__('Kundenfreigabe')">
        @if($timesheet->isSigned() || $timesheet->isLocked())
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="text-sm">
                    <div><strong>{{ $timesheet->customer_name ?: __('Unbekannt') }}</strong> @if($timesheet->customer_role) ({{ $timesheet->customer_role }}) @endif</div>
                    @if($timesheet->customer_email)<div>{{ $timesheet->customer_email }}</div>@endif
                    @if($timesheet->signed_at || $timesheet->signed_ip)
                        <div class="mt-1 text-base-content/60">
                            @if($timesheet->signed_at)
                                {{ __('Signiert am') }}: {{ $timesheet->signed_at?->fdatetime() }}
                            @endif
                            @if($timesheet->signed_ip)
                                @if($timesheet->signed_at) · @endif
                                IP {{ $timesheet->signed_ip }}
                            @endif
                        </div>
                    @endif
                    @if($timesheet->signature_hash)
                        <div class="text-xs break-all text-base-content/50">SHA-256: {{ $timesheet->signature_hash }}</div>
                    @endif
                </div>
                @if($timesheet->signatureAttachment)
                    <div>
                        <img src="{{ \App\Http\Controllers\AttachmentController::downloadUrl($timesheet->signatureAttachment) }}"
                             alt="signature" class="max-h-32 rounded border border-base-300 bg-white p-2">
                    </div>
                @endif
            </div>
        @elseif($canSign)
            @include('timesheets._signature_pad', [
                'action'        => route('projects.timesheets.sign', [$project, $timesheet]),
                'timesheet'     => $timesheet,
            ])
        @else
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">description</span>' :message="__('Stundenzettel ist noch nicht zur Signatur freigegeben.')" />
        @endif
    </x-card>
</x-page-shell>
@endsection
