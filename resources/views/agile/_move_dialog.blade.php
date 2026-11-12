{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _move_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Abschlussdialog eines Arbeitselements (Feature 064, MVP-1608): zeigt offene
  Akzeptanzkriterien, die Definition of Done zum Bestätigen und nimmt die
  Begründung fürs Übersteuern an. Geprüft wird in AgileBoardService::move().
--}}
<x-modal
    :title="__('Verschieben nach „:column“', ['column' => $target->name])"
    :eyebrow="$item->task?->title ?? __('Projektboard')"
    icon="task_alt"
    size="sm"
    :action="route('agile.items.move', [$project, $item])"
    method="PATCH"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Verschieben')"
>
    <input type="hidden" name="column" value="{{ $target->sqid }}">
    <input type="hidden" name="lock_version" value="{{ $item->lock_version }}">

    @if ($wipReached)
        <p class="text-sm text-warning">{{ __('WIP-Limit der Spalte erreicht (:limit).', ['limit' => $target->wip_limit]) }}</p>
    @endif

    @if ($openCriteria->isNotEmpty())
        <div>
            <h3 class="text-sm font-semibold">{{ __('Offene Akzeptanzkriterien') }}</h3>
            <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">
                @foreach ($openCriteria as $criterion)
                    <li>{{ $criterion->text }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($dodItems !== [])
        <fieldset>
            <legend class="text-sm font-semibold">{{ __('Definition of Done') }}</legend>
            @foreach ($dodItems as $position => $text)
                <x-checkbox-field name="dod_confirmed[]" id="dod-confirmed-{{ $position }}" :value="$position" :toggle="false"
                                  :with-hidden="false" :label="$text" :required="! $mayOverride" />
            @endforeach
        </fieldset>
    @endif

    @if ($mayOverride)
        <x-textarea-field name="override_reason" id="move-override-reason" rows="2" maxlength="300"
                          :label="__('Begründung für das Übersteuern')"
                          :hint="__('Nur nötig, wenn Sie trotz offener Punkte verschieben. Die Abweichung wird protokolliert.')">{{ old('override_reason') }}</x-textarea-field>
    @elseif ($wipReached || $openCriteria->isNotEmpty())
        <p class="text-sm text-muted">{{ __('Offene Punkte übersteuern darf nur, wer das Recht dazu hat.') }}</p>
    @endif
</x-modal>
