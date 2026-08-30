{{--
  Created on   : Thu Jul 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Registry-Job innerhalb der erlaubten Kadenzen umplanen (MVP-176) --}}
@php
    /** @var \App\Scheduling\JobDefinition $definition */
    /** @var \App\Scheduling\Cadence $cadence */
    $currentType = old('cadence_type', $cadence->type->value);
@endphp
<x-modal
    :title="__('scheduler.title.reschedule')"
    :eyebrow="$definition->label()"
    icon="schedule"
    tone="primary"
    :action="route('admin.scheduler.update', ['job' => $definition->key])"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('scheduler.action.save')"
>
    <x-form-group :legend="__('scheduler.field.plan')" icon="schedule" tone="primary" cols="2">
        <div class="fieldset">
            <span class="fieldset-label">{{ __('scheduler.field.cadence_type') }}</span>
            <select name="cadence_type" class="select select-bordered w-full" data-scheduler-cadence-select>
                @foreach ($definition->allowedCadences as $type)
                    <option value="{{ $type->value }}" @selected($currentType === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
            @error('cadence_type')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <span class="fieldset-label">{{ __('scheduler.field.time') }}</span>
            <input type="time" name="time" class="input input-bordered w-full"
                   value="{{ old('time', $cadence->time) }}">
            <p class="mt-1 text-xs text-muted">{{ __('scheduler.hint.time') }}</p>
            @error('time')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <span class="fieldset-label">{{ __('scheduler.field.day') }}</span>
            <input type="number" name="day" min="0" max="31" class="input input-bordered w-full"
                   value="{{ old('day', $cadence->day) }}">
            <p class="mt-1 text-xs text-muted">{{ __('scheduler.hint.day') }}</p>
            @error('day')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        @if ($definition->allowsCadence(\App\Scheduling\CadenceType::Cron))
            <div class="fieldset">
                <span class="fieldset-label">{{ __('scheduler.field.expression') }}</span>
                <input aria-label="{{ __('Cron-Ausdruck') }}" type="text" name="expression" class="input input-bordered w-full font-mono"
                       placeholder="0 4 15 1,7 *"
                       value="{{ old('expression', $cadence->expression) }}">
                <p class="mt-1 text-xs text-muted">{{ __('scheduler.hint.expression') }}</p>
                @error('expression')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>
        @endif
    </x-form-group>

    <div role="alert" class="alert alert-warning alert-soft mt-4">
        <x-icon name="warning" />
        <span class="text-sm">{{ __('scheduler.hint.allowlist', ['runtime' => $definition->expectedRuntimeMinutes]) }}</span>
    </div>
</x-modal>
