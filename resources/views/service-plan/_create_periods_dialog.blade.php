{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _create_periods_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- „Perioden des Jahres anlegen“ (Feature 199, MVP-1639) über die gesammelte Folgeperiode (Feature 198). Variablen: $services (Leistungen unter dem Jahr), $year. --}}
<x-modal
    :title="__('service_plan.create_periods.title')"
    :eyebrow="__('service_plan.title')"
    icon="event_repeat"
    tone="primary"
    :action="$services !== [] ? route('service-plan.periods.create') : null"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('service_plan.create_periods.submit')">

    <input type="hidden" name="_dialog_url" value="{{ route('service-plan.periods.create-dialog', ['year' => $year, 'dialog' => 1]) }}">

    <p class="text-sm">{{ __('service_plan.create_periods.intro', ['year' => $year]) }}</p>

    @if ($services === [])
        <x-empty-state compact icon="event_repeat" :title="__('service_plan.create_periods.none')" />
    @else
        <x-form-group :legend="__('service_plan.create_periods.legend')" icon="tune" tone="ghost" cols="2">
            <x-select-field name="service" :label="__('service_plan.column.service')" required span="2">
                @foreach ($services as $column)
                    @php($value = $column->service->param() . '|' . $column->kind->value)
                    <option value="{{ $value }}" @selected(old('service') === $value)>{{ $column->label }} ({{ $column->kind->label() }})</option>
                @endforeach
            </x-select-field>
            <x-input-field type="number" name="year" :label="__('service_plan.filter.year')" min="2000" max="2100" :value="old('year', $year)" required />
        </x-form-group>
        <p class="text-xs text-muted">{{ __('service_plan.create_periods.hint') }}</p>
    @endif

    <x-validation-errors />
</x-modal>
