{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : measurement.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Eingabe Messreihe (MVP-883): jeder Eintrag ergänzt einen Messpunkt mit
     Zeitstempel; bisherige Werte bleiben. --}}
@php $samples = is_array($value) ? $value : []; @endphp
<div class="form-control sm:col-span-2">
    <label class="form-control">
        <span class="label-text">{{ __('protocol.dialog.measurement_add', ['label' => $field->label]) }}{{ $field->unit !== null ? ' (' . $field->unit . ')' : '' }}</span>
        <input type="number" step="any" name="{{ $name }}" required
               @if($field->min !== null) min="{{ $field->min }}" @endif @if($field->max !== null) max="{{ $field->max }}" @endif
               class="input input-bordered w-full @error($errKey) input-error @enderror">
    </label>
    @if ($samples !== [])
        <p class="mt-1 text-xs text-muted">{{ __('protocol.dialog.measurement_count', ['count' => count($samples)]) }}</p>
    @endif
</div>
