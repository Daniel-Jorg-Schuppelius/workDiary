{{--
  Modal (Feature 070): Durchführung einer Übung dokumentieren — Beobachtungen,
  Abweichungen, Wirksamkeit, Folgemaßnahmen. Variablen: $exercise (CrisisExercise).
--}}
<x-modal
    :title="__('Übung dokumentieren')"
    :eyebrow="$exercise->title"
    icon="fact_check"
    tone="primary"
    :action="route('crisis.exercises.document', $exercise)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Dokumentieren')">

    <x-textarea-field name="participants" :label="__('Teilnehmer')" rows="2" maxlength="5000" :value="old('participants')" />

    <x-textarea-field name="observations" :label="__('Beobachtungen')" rows="3" maxlength="10000" :value="old('observations')" />

    <x-textarea-field name="deviations" :label="__('Abweichungen')" rows="3" maxlength="10000" :value="old('deviations')" />

    <x-select-field name="effectiveness" :label="__('Wirksamkeit')" required>
        <option value="effective" @selected(old('effectiveness') === 'effective')>{{ __('values.effective') }}</option>
        <option value="partly" @selected(old('effectiveness') === 'partly')>{{ __('values.partly') }}</option>
        <option value="ineffective" @selected(old('effectiveness') === 'ineffective')>{{ __('values.ineffective') }}</option>
    </x-select-field>

    <x-textarea-field name="follow_up" :label="__('Folgemaßnahmen')" rows="2" maxlength="10000" :value="old('follow_up')" />

    <x-input-field name="next_due_on" type="date" :label="__('Nächste Übung fällig am')" :value="old('next_due_on', optional($exercise->next_due_on)->toDateString())" />
</x-modal>
