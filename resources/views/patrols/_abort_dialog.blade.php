{{--
  Created on   : Mon Oct 05 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _abort_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Laufenden Rundgang abbrechen (Feature 089) — nur mit Begründung.
  Erwartet: $run (PatrolRun mit route).
--}}
<x-modal
    :title="__('Rundgang abbrechen')"
    :eyebrow="$run->route?->name"
    icon="cancel"
    tone="warning"
    :action="route('patrols.runs.abort.store', $run)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Rundgang abbrechen')">
    <p class="text-sm text-muted">{{ __('Der Rundgang endet, ohne als abgeschlossen zu zählen. Bestätigte Kontrollpunkte bleiben als Nachweis stehen; offene gehen als offener Punkt an die Leitstelle.') }}</p>
    <x-textarea-field name="reason" :label="__('Begründung')" rows="3" required maxlength="1000">{{ old('reason') }}</x-textarea-field>
</x-modal>
