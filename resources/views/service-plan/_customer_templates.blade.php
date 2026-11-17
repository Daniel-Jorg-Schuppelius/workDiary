{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _customer_templates.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Leistungsvorlagen im Dialog „Kunde anlegen“ (Feature 199, MVP-1674): angekreuzte werden Leistungsumfang ab der laufenden Periode. Variablen: $templates. --}}
@php($selectedTemplates = array_values(array_filter((array) old('service_templates', []), 'is_string')))
<x-form-group-collapsible :legend="__('service_plan.templates.legend')" icon="calendar_view_month" tone="ghost" :fields="['service_templates']" :values="['service_templates' => $selectedTemplates]">
    <fieldset class="space-y-1 md:col-span-2">
        <legend class="text-xs text-muted">{{ __('service_plan.templates.hint') }}</legend>
        @foreach ($templates as $template)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="service_templates[]" value="{{ $template->sqid }}" class="checkbox checkbox-sm" @checked(in_array($template->sqid, $selectedTemplates, true))>
                <span>{{ $template->displayLabel() }}</span>
                <span class="text-xs text-muted">{{ $template->period_kind->label() }} · {{ $template->is_mandatory ? __('service_plan.mandatory') : __('service_plan.optional') }}</span>
            </label>
        @endforeach
    </fieldset>
</x-form-group-collapsible>
