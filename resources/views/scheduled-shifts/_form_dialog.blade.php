{{-- Variablen: $shift (ScheduledShift), $users, $types --}}
@php
    /**
     * @var \App\Models\ScheduledShift $shift
     * @var \Illuminate\Support\Collection<int, \App\Models\User> $users
     * @var \Illuminate\Support\Collection<int, mixed> $types
     */
    $action = route('scheduled-shifts.update', $shift);
@endphp

<x-modal
    :title="__('Schicht bearbeiten')"
    :eyebrow="$shift->date->fdate() . ' · ' . ($shift->user?->name ?? '—')"
    icon="event"
    tone="primary"
    :action="$action"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    <x-validation-errors />

    <x-form-group :legend="__('Zuordnung')" icon="person" tone="primary" cols="2">
        <x-select-field name="user_id" :label="__('Mitarbeiter')" required>
            @foreach ($users as $u)
                <option value="{{ $u->sqid }}" @selected((string) old('user_id', \App\Support\Sqid::encode(\App\Models\User::class, $shift->user_id)) === $u->sqid)>{{ $u->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="shift_type_id" :label="__('Schichttyp')">
            <option value="">—</option>
            @foreach ($types as $t)
                <option value="{{ $t->sqid }}" @selected((string) old('shift_type_id', \App\Support\Sqid::encode(\App\Models\ShiftType::class, $shift->shift_type_id)) === $t->sqid)>{{ $t->name }} ({{ $t->abbreviation }})</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('Zeitraum & Status')" icon="schedule" tone="info" cols="2">
        <x-input-field name="date" type="date" :label="__('Datum')" required :value="old('date', $shift->date->format('Y-m-d'))" />
        <x-select-field name="status" :label="__('Status')">
            @foreach (\App\Enums\Shift\ScheduledShiftStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected(old('status', $shift->status?->value) === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </x-select-field>
        <x-date-range
            layout="split"
            type="time"
            fromName="start_time"
            toName="end_time"
            :fromLabel="__('Beginn')"
            :toLabel="__('Ende')"
            :from="old('start_time', $shift->start_time)"
            :to="old('end_time', $shift->end_time)"
            formControl
            gridClass="contents"
        />
    </x-form-group>

    <x-form-group :legend="__('Notiz')" icon="description" tone="ghost">
        <x-textarea-field name="note" :label="__('Notiz')" rows="3" :value="old('note', $shift->note)" />
    </x-form-group>

    @can('delete', $shift)
        <x-slot:footerExtra>
            <x-action-form :action="route('scheduled-shifts.destroy', $shift)" method="DELETE"
                  :confirm="__('Wirklich löschen?')"
                  :confirm-label="__('Löschen')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Schicht löschen') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endcan
</x-modal>
