{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : defect.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Eingabe Mangel-Punkt (MVP-883): Schweregrad, Beschreibung, Kategorie.
     Beim ersten Ausfüllen legt der Dienst den offenen Punkt an. --}}
@php $current = is_array($value) ? $value : []; @endphp
<fieldset class="form-control sm:col-span-2">
    <legend class="label-text">{{ $field->label }}</legend>
    <div class="grid gap-3 sm:grid-cols-2">
        <label class="form-control">
            <span class="label-text">{{ __('protocol.dialog.defect_severity') }}</span>
            <select name="{{ $name }}[severity]" required class="select select-bordered w-full">
                @foreach (\App\Services\Protocol\Fields\Extensions\DefectField::SEVERITIES as $severity)
                    <option value="{{ $severity }}" @selected(($current['severity'] ?? 'medium') === $severity)>{{ \App\Enums\OpenIssue\OpenIssueSeverity::from($severity)->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('protocol.dialog.defect_category') }}</span>
            <input type="text" name="{{ $name }}[category]" maxlength="120" class="input input-bordered w-full" value="{{ is_scalar($current['category'] ?? null) ? $current['category'] : '' }}">
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('protocol.dialog.defect_description') }} *</span>
            <textarea name="{{ $name }}[description]" rows="3" required maxlength="5000" class="textarea textarea-bordered w-full @error($errKey) textarea-error @enderror">{{ is_scalar($current['description'] ?? null) ? $current['description'] : '' }}</textarea>
        </label>
    </div>
</fieldset>
