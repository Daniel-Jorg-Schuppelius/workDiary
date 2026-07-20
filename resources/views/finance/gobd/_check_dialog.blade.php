{{--
  Created on   : Mon Jul 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _check_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Auswahl-Dialog GoBD-Z3 (Feature 063, MVP-132): Prüfungszeitraum + Datenbereiche.
  Bewusst ein natives GET-Formular (kein data-entry-form) — der Submit navigiert
  auf finance.gobd.index?from&to&sections[], sodass die Vorprüfung serverseitig
  berechnet und inline auf der Seite angezeigt wird.
--}}

<x-modal
    :title="__('gobd.preflight.check')"
    :eyebrow="__('gobd.preflight.title')"
    icon="fact_check"
    tone="primary">

    <form id="gobd-check-form" method="GET" action="{{ route('finance.gobd.index') }}" class="space-y-4">
        <x-form-group :legend="__('gobd.period')" icon="date_range" tone="primary">
            <x-date-range from-name="from" to-name="to" :from="$from" :to="$to" form-control />
        </x-form-group>

        <x-form-group :legend="__('gobd.sections')" icon="dataset" tone="ghost">
            <div class="grid w-full grid-cols-1 gap-x-6 gap-y-1.5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($sections as $key)
                    <label class="label cursor-pointer justify-start gap-2 py-1">
                        <input type="checkbox" name="sections[]" value="{{ $key }}"
                               class="checkbox checkbox-sm checkbox-primary"
                               @checked(in_array($key, $selected, true))>
                        <span class="label-text">{{ __('gobd.section.' . $key) }}</span>
                    </label>
                @endforeach
            </div>
        </x-form-group>
    </form>

    <x-slot:actions>
        <button type="button" class="btn btn-ghost gap-2" data-entry-modal-close>
            <x-icon name="close" /> {{ __('Abbrechen') }}
        </button>
        <button type="submit" form="gobd-check-form" class="btn btn-primary gap-2">
            <x-icon name="fact_check" /> {{ __('gobd.preflight.check') }}
        </button>
    </x-slot:actions>
</x-modal>
