@php
    /** @var array<string, mixed> $field */
    $key = $field['key'];
    // Secret-Felder werden unabhängig vom Typ maskiert und nie zurückgerendert (W1d).
    $isSecret = (bool) ($field['secret'] ?? (($field['type'] ?? '') === 'password'));
    $current = $isSecret ? null : old('settings.' . $key, data_get($setting->settings, $key, $field['default'] ?? null));
@endphp
<div class="fieldset">
    <label class="fieldset-label">{{ $field['label'] }}@if (! empty($field['required'])) *@endif</label>

    @if ($isSecret)
        <input type="password" name="settings[{{ $key }}]"
               class="input input-sm input-bordered w-full"
               placeholder="@if (! empty(data_get($setting->settings, $key))){{ __('(unverändert — leer lassen)') }}@endif"
               autocomplete="new-password">
    @elseif ($field['type'] === 'boolean')
        <label class="cursor-pointer gap-3 justify-start label">
            <input type="hidden" name="settings[{{ $key }}]" value="0">
            <input type="checkbox" name="settings[{{ $key }}]" value="1" class="toggle"
                   @checked((bool) $current)>
            <span class="label-text">{{ __('Aktiv') }}</span>
        </label>
    @elseif ($field['type'] === 'select')
        <select name="settings[{{ $key }}]" class="select select-sm select-bordered w-full">
            @foreach (($field['options'] ?? []) as $value => $label)
                <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
    @elseif ($field['type'] === 'number')
        <input type="number" name="settings[{{ $key }}]"
               class="input input-sm input-bordered w-full"
               value="{{ $current }}" step="any">
    @elseif ($field['type'] === 'url')
        <input type="url" name="settings[{{ $key }}]"
               class="input input-sm input-bordered w-full"
               value="{{ $current }}" placeholder="https://">
    @elseif ($field['type'] === 'textarea')
        <textarea name="settings[{{ $key }}]" rows="4"
                  class="textarea textarea-sm textarea-bordered w-full">{{ $current }}</textarea>
    @else
        <input type="text" name="settings[{{ $key }}]"
               class="input input-sm input-bordered w-full"
               value="{{ $current }}">
    @endif

    @if (! empty($field['help']))
        <p class="text-xs text-base-content/60 mt-1">{{ $field['help'] }}</p>
    @endif
    @error('settings.' . $key)<p class="text-error text-sm">{{ $message }}</p>@enderror
</div>
