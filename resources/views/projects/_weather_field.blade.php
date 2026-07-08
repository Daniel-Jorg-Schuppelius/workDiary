{{--
  Wetter-Auto-Abruf-Override (Feature 062, Rang 12): Tri-State erben/an/aus.

  Als eigenes Partial ausgelagert: _form_dialog.blade.php lag mit diesem
  Block über der Backtracking-Schwelle des Blade-ComponentTagCompilers —
  das letzte <x-form-group> wurde nicht mehr kompiliert (offenes if im
  Kompilat). Kleine Partials bleiben sicher darunter.
--}}
@php
    $weatherCurrent = $project?->weather_auto_fetch === null ? '' : ($project->weather_auto_fetch ? '1' : '0');
    $weatherValue = (string) old('weather_auto_fetch', $weatherCurrent);
    $weatherInherit = $weatherValue === '';
@endphp
<x-form-group :legend="__('Wetter-Auto-Abruf')" icon="partly_cloudy_day" tone="info">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Automatischer Wetter-Abruf') }}</label>
        <select name="weather_auto_fetch" class="select select-bordered w-full">
            <option value="" @selected($weatherInherit)>{{ __('Erben (Org-Einstellung)') }}</option>
            <option value="1" @selected($weatherValue === '1')>{{ __('An') }}</option>
            <option value="0" @selected($weatherValue === '0')>{{ __('Aus') }}</option>
        </select>
        <p class="text-xs text-base-content/60">{{ __('Überschreibt die Org-Einstellung für dieses Projekt und Sub-Projekte; Erben nutzt den Org-Standard.') }}</p>
        @error('weather_auto_fetch')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
</x-form-group>
