{{--
  Created on   : Mon Oct 05 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _measure_status_form.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Statuspflege einer Maßnahme als Zeilenformular (Dashboard-Karte und Maßnahmenliste).
     Erwartet: $measure; $origin = 'list' führt nach dem Speichern in die Liste zurück. --}}
<form method="POST" action="{{ route('sustainability.measures.update', $measure) }}" class="ml-auto flex items-center justify-end gap-1">
    @csrf @method('PUT')
    @isset($origin)
        <input type="hidden" name="origin" value="{{ $origin }}">
    @endisset
    <select name="status" class="select select-xs select-bordered" aria-label="{{ __('Status') }}">
        @foreach (\App\Enums\Sustainability\SustainabilityMeasureStatus::cases() as $option)
            <option value="{{ $option->value }}" @selected($measure->status === $option)>{{ $option->label() }}</option>
        @endforeach
    </select>
    @if ($measure->status === \App\Enums\Sustainability\SustainabilityMeasureStatus::Done && $measure->effectiveness === null)
        <select name="effectiveness" class="select select-xs select-bordered" aria-label="{{ __('Wirksamkeit') }}">
            <option value="">{{ __('Wirksamkeit …') }}</option>
            <option value="effective">{{ __('values.effective') }}</option>
            <option value="partly">{{ __('values.partly') }}</option>
            <option value="ineffective">{{ __('values.ineffective') }}</option>
        </select>
    @endif
    <x-button type="submit" tone="plain" size="xs">{{ __('OK') }}</x-button>
</form>
