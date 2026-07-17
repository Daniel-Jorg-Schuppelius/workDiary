{{--
  Created on   : Fri Jul 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erwartet: $scopeDate, $canCreateForOthers, $members, $targetTypes, $actions, $isDialog
--}}
@php
    $isDialog = $isDialog ?? false;
    $firstType = array_key_first($targetTypes);
    $itemTemplate = [
        'target_type' => $firstType,
        'target_id' => '',
        'action' => 'create',
        'before' => '',
        'after' => '',
    ];
    $itemItems = old('items');
    if (! is_array($itemItems) || $itemItems === []) {
        $itemItems = [$itemTemplate];
    }
@endphp

<x-modal
    :title="__('Korrekturantrag anlegen')"
    :eyebrow="__('Korrekturantrag')"
    icon="edit_note"
    tone="primary"
    :action="route('corrections.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Als Entwurf speichern')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ route('corrections.create') }}">
    @endif

    <x-form-group :legend="__('Antragsdaten')" icon="edit_note" tone="primary" cols="2">
        @if ($canCreateForOthers ?? false)
            <x-select-field name="user_id" span="2" :label="__('Für Mitarbeiter:in')">
                <option value="">{{ __('— mich selbst —') }}</option>
                @foreach ($members as $m)
                    <option value="{{ $m->sqid }}" @selected((string) old('user_id') === $m->sqid)>{{ $m->name }}</option>
                @endforeach
            </x-select-field>
        @endif
        <x-input-field name="scope_date" type="date" :label="__('Bezugsdatum')"
                       :value="old('scope_date', $scopeDate->format('Y-m-d'))" required />
        <x-textarea-field name="reason" span="2" :label="__('Begründung (≥ 20 Zeichen)')"
                          rows="3" minlength="20" maxlength="4000" :value="old('reason')" required />
    </x-form-group>

    <x-form-group :legend="__('Items')" icon="checklist" tone="info">
        <div x-data="repeater"
             data-prefix="items"
             data-items="{{ json_encode($itemItems) }}"
             data-template="{{ json_encode($itemTemplate) }}"
             class="space-y-3">
            <template x-for="(it, i) in items" :key="i">
                <div class="rounded-box border border-base-300 bg-base-200/40 p-3 space-y-2">
                    <div class="grid gap-2 md:grid-cols-3">
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('Ziel-Typ') }}</label>
                            <select :name="fieldName(i, 'target_type')" x-model="it.target_type"
                                    class="select select-sm select-bordered w-full">
                                @foreach ($targetTypes as $cls => $label)
                                    <option value="{{ $cls }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('Ziel-ID (leer für create)') }}</label>
                            <input type="number" :name="fieldName(i, 'target_id')" x-model="it.target_id"
                                   class="input input-sm input-bordered w-full">
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('Aktion') }}</label>
                            <select :name="fieldName(i, 'action')" x-model="it.action"
                                    class="select select-sm select-bordered w-full">
                                @foreach ($actions as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid gap-2 md:grid-cols-2">
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('Vorher (JSON, optional)') }}</label>
                            <textarea :name="fieldName(i, 'before')" x-model="it.before" rows="3"
                                      class="textarea textarea-bordered textarea-sm w-full font-mono text-xs"
                                      placeholder='{"minutes": 60}'></textarea>
                        </div>
                        <div class="fieldset">
                            <label class="fieldset-label">{{ __('Nachher (JSON)') }}</label>
                            <textarea :name="fieldName(i, 'after')" x-model="it.after" rows="3"
                                      class="textarea textarea-bordered textarea-sm w-full font-mono text-xs"
                                      placeholder='{"minutes": 90}'></textarea>
                        </div>
                    </div>
                    <div class="text-right">
                        <x-icon-btn icon="delete" tone="ghost" size="xs" type="button"
                                    x-show="items.length > 1" @click="remove(i)"
                                    show-label>{{ __('Item entfernen') }}</x-icon-btn>
                    </div>
                </div>
            </template>

            <x-icon-btn icon="add" tone="ghost" size="sm" type="button" show-label @click="add()">
                {{ __('Item hinzufügen') }}
            </x-icon-btn>
        </div>
    </x-form-group>
</x-modal>
