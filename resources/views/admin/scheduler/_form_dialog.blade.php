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
    /** @var array<string, mixed> $override */
    $currentType = old('cadence_type', $cadence->type->value);
    $override = $override ?? [];
    $currentTimezone = (string) old('timezone', $override['timezone'] ?? '');
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
            <p class="mt-1 text-xs text-muted">{{ __('scheduler.hint.time', ['tz' => config('app.schedule_timezone', config('app.timezone'))]) }}</p>
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

    <x-form-group :legend="__('scheduler.field.run_options')" icon="tune" tone="info" cols="2">
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label" for="scheduler-timezone">{{ __('scheduler.field.timezone') }}</label>
            <select id="scheduler-timezone" name="timezone" class="select select-bordered w-full">
                <option value="">{{ __('scheduler.hint.timezone_default', ['tz' => config('app.schedule_timezone', config('app.timezone'))]) }}</option>
                @foreach (\DateTimeZone::listIdentifiers() as $zone)
                    <option value="{{ $zone }}" @selected($currentTimezone === $zone)>{{ $zone }}</option>
                @endforeach
            </select>
            @error('timezone')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="scheduler-quiet-from">{{ __('scheduler.field.quiet_from') }}</label>
            <input id="scheduler-quiet-from" type="time" name="quiet_from" class="input input-bordered w-full"
                   value="{{ old('quiet_from', $override['quiet_from'] ?? '') }}">
            @error('quiet_from')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
        <div class="fieldset">
            <label class="fieldset-label" for="scheduler-quiet-until">{{ __('scheduler.field.quiet_until') }}</label>
            <input id="scheduler-quiet-until" type="time" name="quiet_until" class="input input-bordered w-full"
                   value="{{ old('quiet_until', $override['quiet_until'] ?? '') }}">
            @error('quiet_until')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
        <p class="text-xs text-muted md:col-span-2">
            {{ $installationWindow !== null
                ? __('scheduler.hint.quiet_window', ['window' => $installationWindow])
                : __('scheduler.hint.quiet_window_none') }}
        </p>

        <div class="fieldset">
            <label class="fieldset-label" for="scheduler-retry-attempts">{{ __('scheduler.field.retry_attempts') }}</label>
            <input id="scheduler-retry-attempts" type="number" name="retry_attempts" min="0" max="{{ \App\Scheduling\SchedulerOverrideService::MAX_RETRIES }}"
                   class="input input-bordered w-full" value="{{ old('retry_attempts', $override['retry_attempts'] ?? 0) }}">
            @error('retry_attempts')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
        <div class="fieldset">
            <label class="fieldset-label" for="scheduler-retry-delay">{{ __('scheduler.field.retry_delay_minutes') }}</label>
            <input id="scheduler-retry-delay" type="number" name="retry_delay_minutes" min="1" max="240"
                   placeholder="{{ \App\Scheduling\SchedulerOverrideService::DEFAULT_RETRY_DELAY }}"
                   class="input input-bordered w-full" value="{{ old('retry_delay_minutes', $override['retry_delay_minutes'] ?? '') }}">
            @error('retry_delay_minutes')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
        <p class="text-xs text-muted md:col-span-2">{{ __('scheduler.hint.retry') }}</p>
    </x-form-group>

    <div role="alert" class="alert alert-warning alert-soft mt-4">
        <x-icon name="warning" />
        <span class="text-sm">{{ __('scheduler.hint.allowlist', ['runtime' => $definition->expectedRuntimeMinutes]) }}</span>
    </div>
</x-modal>
