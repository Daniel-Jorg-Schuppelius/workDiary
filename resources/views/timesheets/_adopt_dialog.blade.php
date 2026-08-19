{{--
  Created on   : Wed Aug 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _adopt_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Übernahme bereits erfasster Zeiten in den Stundenzettel.
     Erwartet: $project, $timesheet, $candidates (Collection<TimeEntry>) --}}
@php
    $fmtMin = fn (int $min): string => \App\Support\Formats::duration($min, 'clock', withUnit: false);
@endphp
<x-modal
    :title="__('Zeiten übernehmen')"
    :eyebrow="optional($timesheet->work_date)->fdate()"
    icon="playlist_add_check"
    tone="primary"
    :action="$candidates->isEmpty() ? null : route('projects.timesheets.entries.adopt', [$project, $timesheet])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Übernehmen')"
>
    @if ($candidates->isEmpty())
        <x-empty-state compact
                       :title="__('Keine offenen Zeiten')"
                       :message="__('Für dieses Projekt und diesen Tag gibt es keine erfassten Zeiten, die noch an keinem Stundenzettel hängen.')" />
    @else
        <p class="mb-3 text-sm text-base-content/70">
            {{ __('Diese Zeiten sind bereits erfasst (Stoppuhr, Tagesansicht oder Import), hängen aber an keinem Stundenzettel. Ausgewählte Zeilen werden diesem Zettel zugeordnet.') }}
        </p>

        <ul class="space-y-2 max-h-[55vh] overflow-y-auto pr-1">
            @foreach ($candidates as $entry)
                <li class="rounded-box border border-base-300 bg-base-100">
                    <label class="flex cursor-pointer items-start gap-3 px-3 py-2.5">
                        <input type="checkbox" name="entry_ids[]" value="{{ $entry->sqid }}"
                               checked class="checkbox checkbox-sm mt-0.5">
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-baseline gap-x-2">
                                <span class="text-sm font-medium tabular-nums">
                                    @if ($entry->started_at && $entry->ended_at)
                                        {{ $entry->started_at->ftime() }}–{{ $entry->ended_at->ftime() }}
                                    @else
                                        {{ $fmtMin((int) $entry->minutes) }}
                                    @endif
                                </span>
                                <span class="text-xs text-base-content/60">
                                    {{ $fmtMin((int) $entry->minutes) }} h
                                    @if ((int) $entry->break_minutes > 0)
                                        · {{ __('Pause') }} {{ (int) $entry->break_minutes }} {{ __('Min.') }}
                                    @endif
                                    @if ($entry->kind)
                                        · {{ $entry->kind->label() }}
                                    @endif
                                </span>
                            </span>
                            <span class="block truncate text-sm text-base-content/80">
                                {{ $entry->description ?: __('Ohne Beschreibung') }}
                            </span>
                            @if ($entry->task)
                                <span class="block truncate text-xs text-base-content/50">{{ $entry->task->title }}</span>
                            @endif
                        </span>
                    </label>
                </li>
            @endforeach
        </ul>
    @endif
</x-modal>
