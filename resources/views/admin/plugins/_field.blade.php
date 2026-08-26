{{--
  Created on   : Tue May 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _field.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    /** @var array<string, mixed> $field */
    $key = $field['key'];
    // Secret-Felder werden unabhängig vom Typ maskiert und nie zurückgerendert (W1d).
    $isSecret = (bool) ($field['secret'] ?? (($field['type'] ?? '') === 'password'));
    $current = $isSecret ? null : old('settings.' . $key, data_get($setting->settings, $key, $field['default'] ?? null));
    // Eine id je Feld — das Label verweist darauf, egal welcher Zweig rendert.
    $fieldId = 'plugin-setting-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $key);
@endphp
<div class="fieldset">
    <label class="fieldset-label" for="{{ $fieldId }}">{{ $field['label'] }}@if (! empty($field['required'])) *@endif</label>

    @if ($isSecret)
        <input type="password" id="{{ $fieldId }}" name="settings[{{ $key }}]"
               class="input input-sm input-bordered w-full"
               placeholder="@if (! empty(data_get($setting->settings, $key))){{ __('(unverändert — leer lassen)') }}@endif"
               autocomplete="new-password">
        @if (! empty(data_get($setting->settings, $key)))
            {{-- Nur bei gesetztem Wert: erlaubt das Entfernen des gespeicherten
                 Secrets (Rückfall auf Config/ENV) — überschreiben geht über das
                 Feld selbst. Greift nur bei leerem Eingabefeld. --}}
            <label class="mt-1 flex items-center gap-2 text-xs text-muted">
                <input type="checkbox" name="settings_reset[{{ $key }}]" value="1" class="checkbox checkbox-xs">
                {{ __('Gespeicherten Wert löschen (auf Standard/ENV zurücksetzen)') }}
            </label>
        @endif
    @elseif ($field['type'] === 'boolean')
        <label class="cursor-pointer gap-3 justify-start label">
            <input type="hidden" name="settings[{{ $key }}]" value="0">
            <input type="checkbox" id="{{ $fieldId }}" name="settings[{{ $key }}]" value="1" class="toggle"
                   @checked((bool) $current)>
            <span class="label-text">{{ __('Aktiv') }}</span>
        </label>
    @elseif ($field['type'] === 'select')
        <select id="{{ $fieldId }}" name="settings[{{ $key }}]" class="select select-sm select-bordered w-full">
            @foreach (($field['options'] ?? []) as $value => $label)
                <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
    @elseif ($field['type'] === 'number')
        <input type="number" id="{{ $fieldId }}" name="settings[{{ $key }}]"
               class="input input-sm input-bordered w-full"
               value="{{ $current }}" step="any">
    @elseif ($field['type'] === 'url')
        <input type="url" id="{{ $fieldId }}" name="settings[{{ $key }}]"
               class="input input-sm input-bordered w-full"
               value="{{ $current }}" placeholder="https://">
    @elseif ($field['type'] === 'textarea')
        <textarea id="{{ $fieldId }}" name="settings[{{ $key }}]" rows="4"
                  class="textarea textarea-sm textarea-bordered w-full">{{ $current }}</textarea>
    @else
        <input type="text" id="{{ $fieldId }}" name="settings[{{ $key }}]"
               class="input input-sm input-bordered w-full"
               value="{{ $current }}">
    @endif

    @if (! empty($field['help']))
        <p class="text-xs text-muted mt-1">{{ $field['help'] }}</p>
    @endif
    @error('settings.' . $key)<p class="text-error text-sm">{{ $message }}</p>@enderror
</div>
