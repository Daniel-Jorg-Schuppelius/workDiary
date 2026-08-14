{{--
  Created on   : Thu May 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : user-select.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'name',
    'users' => [],
    'selected' => null,
    'placeholder' => null,
    'required' => false,
    'includeBlank' => true,
    'id' => null,
    'labelKey' => 'name',
    'valueKey' => 'id',
])

@php
    $selectId = $id ?? $name;
    $placeholderText = $placeholder ?? __('Benutzer auswählen…');
    $errorKey = preg_replace('/\\[\\]$/', '', $name);
@endphp

<select id="{{ $selectId }}"
        name="{{ $name }}"
        @if($required) required @endif
        {{ $attributes->merge(['class' => 'select select-bordered w-full' . ($errors->has($errorKey) ? ' select-error' : '')]) }}>
    @if ($includeBlank)
        <option value="">{{ $placeholderText }}</option>
    @endif

    @foreach ($users as $user)
        @php
            $value = is_array($user) ? ($user[$valueKey] ?? null) : ($user->{$valueKey} ?? null);
            $label = is_array($user) ? ($user[$labelKey] ?? $value) : ($user->{$labelKey} ?? $value);
        @endphp
        <option value="{{ $value }}" @selected((string) old($errorKey, $selected) === (string) $value)>
            {{ $label }}
        </option>
    @endforeach
</select>

@error($errorKey)
    <p class="mt-1 text-xs text-error">{{ $message }}</p>
@enderror
