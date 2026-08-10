{{-- Massen-Neuzuordnung (MVP-508) — erwartet: $project, $entries, $blocked, $missing, $targets, $isDialog --}}
@php
    $isDialog = $isDialog ?? false;
    $action = route('projects.time-entries.reassign', $project);
    $sqids = $entries->pluck('sqid')->all();
    $dialogUrl = route('projects.time-entries.reassign-dialog', $project)
        . '?' . http_build_query(['ids' => $sqids, 'dialog' => 1]);

    $totalMinutes = (int) $entries->sum('minutes');
    $byUser = $entries->groupBy(fn ($e) => $e->user->name ?? '—')->map->count()->sortDesc();
    $hasBlockers = $blocked !== [] || $entries->isEmpty();
@endphp

<x-modal
    :title="__('Benutzer zuordnen')"
    :eyebrow="__('Zeiterfassung')"
    icon="person_add"
    tone="primary"
    :action="$action"
    method="POST"
    form-id="time-reassign-form"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Zuordnen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    @foreach ($entries as $entry)
        <input type="hidden" name="ids[]" value="{{ $entry->sqid }}" form="time-reassign-form">
    @endforeach

    {{-- Zusammenfassung der Auswahl --}}
    <div class="rounded-box border border-base-300 bg-base-200/40 px-4 py-3 text-sm">
        <p class="font-medium">
            {{ trans_choice(':n Zeiteintrag|:n Zeiteinträge', $entries->count(), ['n' => $entries->count()]) }}
            · {{ \App\Support\Formats::duration($totalMinutes, 'clock') }}
        </p>
        @if ($byUser->isNotEmpty())
            <p class="mt-1 text-base-content/70">
                {{ __('Bisher zugeordnet:') }}
                {{ $byUser->map(fn ($count, $name) => $name . ' (' . $count . ')')->implode(', ') }}
            </p>
        @endif
    </div>

    @if ($missing > 0)
        <div class="alert alert-warning alert-soft text-sm">
            <x-icon name="warning" />
            <span>{{ trans_choice(':n Eintrag der Auswahl gehört nicht (mehr) zu diesem Projekt und wurde entfernt.|:n Einträge der Auswahl gehören nicht (mehr) zu diesem Projekt und wurden entfernt.', $missing, ['n' => $missing]) }}</span>
        </div>
    @endif

    @if ($blocked !== [])
        <div class="alert alert-error alert-soft items-start text-sm">
            <x-icon name="lock" />
            <div>
                <p class="font-medium">{{ __('Gesperrte Einträge in der Auswahl — bitte Auswahl bereinigen:') }}</p>
                <ul class="mt-1 list-inside list-disc">
                    @foreach ($blocked as $item)
                        <li>
                            {{ $item['entry']->date?->fdate() ?? '—' }}
                            · {{ $item['entry']->user->name ?? '—' }}
                            — {{ app(\App\Services\Timekeeping\TimeEntryEditPolicy::class)->reasonLabel($item['reason']) }}
                        </li>
                    @endforeach
                </ul>
                <p class="mt-1 text-base-content/70">{{ __('Abgerechnete oder signierte Zeiten können nicht umgehängt werden; die Aktion speichert nie teilweise.') }}</p>
            </div>
        </div>
    @endif

    @if ($entries->isNotEmpty())
        <div class="fieldset">
            <label class="fieldset-label" for="reassign-target">{{ __('Neuer Benutzer') }}</label>
            <select id="reassign-target" name="target_user_id" class="select select-bordered w-full" required>
                <option value="">{{ __('Benutzer wählen…') }}</option>
                @foreach ($targets as $target)
                    <option value="{{ $target->sqid }}" @selected(old('target_user_id') === $target->sqid)>{{ $target->name }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-base-content/60">{{ __('Zur Auswahl stehen nur aktive interne Benutzer dieser Organisation. Sätze und interne Kosten werden für den neuen Benutzer neu berechnet; Fremdsystem-Referenzen bleiben unverändert.') }}</p>
            @error('target_user_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
            @error('ids')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
    @else
        <div class="alert alert-warning alert-soft text-sm">
            <x-icon name="info" />
            <span>{{ __('Keine zuordenbaren Einträge in der Auswahl.') }}</span>
        </div>
    @endif

    <x-slot:actions>
        <button type="button" class="btn btn-ghost gap-2" data-entry-modal-close>
            <x-icon name="close" /> {{ __('Abbrechen') }}
        </button>
        <button type="submit" form="time-reassign-form" class="btn btn-primary gap-2" @disabled($hasBlockers)>
            <x-icon name="check" /> {{ __('Zuordnen') }}
        </button>
    </x-slot:actions>
</x-modal>
