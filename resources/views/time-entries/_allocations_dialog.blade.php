{{--
  Created on   : Wed Aug 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _allocations_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Zeitaufteilung eines Eintrags (Feature 103, MVP-514) --}}
@php
    /** @var \App\Models\TimeEntry $entry */
    $existing = $entry->allocations->values();
    $rowCount = max(3, $existing->count() + 2);
@endphp
<x-modal
    :title="__('allocation.title')"
    :eyebrow="$entry->hoursFormatted()"
    icon="call_split"
    tone="primary"
    :action="route('time-entries.allocations.update', $entry)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('allocation.action.save')"
>
    <p class="mb-2 text-sm text-muted">
        {{ __('allocation.entry_duration') }}: <strong>{{ $entry->hoursFormatted() }}</strong> ·
        {{ __('allocation.hint') }}
    </p>
    @error('allocations')<p class="mb-2 text-sm text-error">{{ $message }}</p>@enderror

    <div class="space-y-2">
        @for ($i = 0; $i < $rowCount; $i++)
            @php
                /** @var \App\Models\TimeAllocation|null $allocation */
                $allocation = $existing[$i] ?? null;
                $selected = $allocation !== null && $allocation->typeAlias() !== null
                    ? $allocation->typeAlias() . ':' . \App\Support\Sqid::encode($allocation->allocatable_type, $allocation->allocatable_id)
                    : '';
            @endphp
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,2fr)_6rem_6rem_minmax(0,1fr)]">
                <select name="allocations[{{ $i }}][target]" class="select select-bordered select-sm w-full"
                        aria-label="{{ __('allocation.target') }}">
                    <option value="">{{ __('allocation.none_option') }}</option>
                    @foreach ($targetGroups as $group)
                        <optgroup label="{{ $group['label'] }}">
                            @foreach ($group['options'] as $option)
                                <option value="{{ $option['value'] }}" @selected($selected === $option['value'])>{{ $option['name'] }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <input type="number" name="allocations[{{ $i }}][minutes]" min="0" step="1"
                       value="{{ $allocation?->duration_minutes }}"
                       placeholder="{{ __('allocation.minutes') }}"
                       aria-label="{{ __('allocation.minutes') }}"
                       class="input input-bordered input-sm w-full">
                <input type="number" name="allocations[{{ $i }}][quantity]" min="0" step="0.01"
                       value="{{ $allocation?->quantity }}"
                       placeholder="{{ __('allocation.quantity') }}"
                       aria-label="{{ __('allocation.quantity') }}"
                       class="input input-bordered input-sm w-full">
                <input type="text" name="allocations[{{ $i }}][comment]" maxlength="255"
                       value="{{ $allocation?->comment }}"
                       placeholder="{{ __('allocation.comment') }}"
                       aria-label="{{ __('allocation.comment') }}"
                       class="input input-bordered input-sm w-full">
            </div>
        @endfor
    </div>
</x-modal>
