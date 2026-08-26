{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _transition_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Status-Card (Feature 065, MVP-156, Muster Ticket-Transition-Card):
  Optionen kommen aus ProblemService::TRANSITIONS (einzige Wahrheit,
  Controller reicht $transitions durch); beim Ziel „resolved" wird die
  Pflichtfrist für die Wirksamkeitsprüfung eingeblendet (Service erzwingt).
  Erwartet: $problem, $transitions (list<string>), $statusLabels.
--}}

<x-card>
    <h3 class="font-semibold mb-3">{{ __('Status ändern') }}</h3>
    {{-- Ziel-Umschaltung via Alpine.data("reveal") (components.js) — CSP-Build-konform. --}}
    <form method="POST" action="{{ route('servicedesk.problems.transition', $problem) }}"
          class="flex flex-wrap items-end gap-2"
          x-data="reveal(@js(old('status', $transitions[0] ?? '')))">
        @csrf
        <select name="status" class="select select-sm select-bordered" x-model="value" aria-label="{{ __('Status') }}">
            @foreach ($transitions as $target)
                <option value="{{ $target }}">{{ $statusLabels[$target] ?? $target }}</option>
            @endforeach
        </select>

        <template x-if="is('resolved')">
            <div class="flex flex-col">
                <label class="fieldset-label" for="effectiveness-due">{{ __('Frist Wirksamkeitsprüfung') }}</label>
                <input id="effectiveness-due" type="datetime-local" name="effectiveness_check_due_at"
                       value="{{ old('effectiveness_check_due_at') }}"
                       required class="input input-sm input-bordered" />
            </div>
        </template>

        <x-button tone="primary" size="sm" type="submit">{{ __('Übernehmen') }}</x-button>
        @error('status')<p class="text-error text-xs w-full">{{ $message }}</p>@enderror
        @error('effectiveness_check_due_at')<p class="text-error text-xs w-full">{{ $message }}</p>@enderror
    </form>
    <p class="text-xs text-muted mt-2">{{ __('Pflicht beim Lösen: Termin, an dem die Wirkung der Lösung geprüft wird.') }}</p>
</x-card>
