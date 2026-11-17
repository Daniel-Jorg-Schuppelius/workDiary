{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _status_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Selbstauskunft am Anwesenheits-Board (Feature 103, MVP-1669). Variablen: $current (PresenceStatus|null), $durations (Minuten). --}}
@php
    $selectedKind = (string) old('kind', $current?->kind->value ?? \App\Enums\Attendance\PresenceStatusKind::BriefAway->value);
    $selectedDuration = (string) old('duration', '30');
    $untilValue = old('valid_until');
@endphp
<x-modal
    :title="__('presence.dialog.title')"
    :eyebrow="__('presence.board')"
    icon="person_pin_circle"
    tone="primary"
    :action="route('presence.status.update')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('presence.dialog.submit')">

    <input type="hidden" name="_dialog_url" value="{{ route('presence.status.edit') . '?dialog=1' }}">

    <div role="note" class="alert alert-info mb-3 text-sm">
        <x-icon name="info" />
        <span>{{ __('presence.dialog.hint') }}</span>
    </div>
    @if ($current !== null)
        <p class="mb-3 text-sm">{{ __('presence.dialog.current') }}: <x-status-badge size="sm" :tone="$current->kind->tone()">{{ app(\App\Services\Attendance\PresenceStatusService::class)->describe($current) }}</x-status-badge></p>
    @endif

    <x-form-group :legend="__('presence.dialog.kind')" icon="person_pin_circle" tone="primary">
        <x-select-field name="kind" id="presence-kind" :label="__('presence.dialog.kind')" required>
            @foreach (\App\Enums\Attendance\PresenceStatusKind::cases() as $kind)
                <option value="{{ $kind->value }}" @selected($selectedKind === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="duration" id="presence-duration" :label="__('presence.dialog.duration')">
            <option value="">{{ __('presence.dialog.no_duration') }}</option>
            @foreach ($durations as $minutes)
                <option value="{{ $minutes }}" @selected($selectedDuration === (string) $minutes)>{{ __('presence.duration.' . $minutes) }}</option>
            @endforeach
        </x-select-field>
        <x-input-field type="datetime-local" id="presence-until" name="valid_until" :label="__('presence.dialog.until')"
                       :value="$untilValue" :hint="__('presence.dialog.until_hint')" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
