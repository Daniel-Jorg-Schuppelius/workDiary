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
    :eyebrow="$shift->date->format('d.m.Y') . ' · ' . ($shift->user?->name ?? '—')"
    icon="event"
    tone="primary"
    :action="$action"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <x-form-group :legend="__('Zuordnung')" icon="person" tone="primary" cols="2">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Mitarbeiter') }} *</label>
            <select name="user_id" required class="select select-bordered w-full">
                @foreach ($users as $u)
                    <option value="{{ $u->sqid }}" @selected((string) old('user_id', sqid(\App\Models\User::class, $shift->user_id)) === $u->sqid)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Schichttyp') }}</label>
            <select name="shift_type_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($types as $t)
                    <option value="{{ $t->id }}" @selected(old('shift_type_id', $shift->shift_type_id) == $t->id)>{{ $t->name }} ({{ $t->abbreviation }})</option>
                @endforeach
            </select>
        </div>
    </x-form-group>

    <x-form-group :legend="__('Zeitraum & Status')" icon="schedule" tone="info" cols="2">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Datum') }} *</label>
            <input type="date" name="date" required
                   value="{{ old('date', $shift->date->format('Y-m-d')) }}"
                   class="input input-bordered w-full">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Status') }}</label>
            <select name="status" class="select select-bordered w-full">
                @foreach (\App\Enums\Shift\ScheduledShiftStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(old('status', $shift->status?->value) === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
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
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Notiz') }}</label>
            <textarea name="note" rows="3" class="textarea textarea-bordered w-full">{{ old('note', $shift->note) }}</textarea>
        </div>
    </x-form-group>

    @can('delete', $shift)
        <x-slot:footerExtra>
            <form method="POST" action="{{ route('scheduled-shifts.destroy', $shift) }}" class="inline"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Wirklich löschen?') }}"
                  data-confirm-label="{{ __('Löschen') }}">
                @csrf @method('DELETE')
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Schicht löschen') }}</x-icon-btn>
            </form>
        </x-slot:footerExtra>
    @endcan
</x-modal>
