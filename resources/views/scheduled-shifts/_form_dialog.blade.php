{{-- Variablen: $shift (ScheduledShift), $users, $types --}}
@php
    $action = route('scheduled-shifts.update', $shift);
@endphp

<x-dialog
    :title="__('Schicht bearbeiten')"
    :eyebrow="$shift->date->format('d.m.Y') . ' · ' . ($shift->user?->name ?? '—')"
    icon="📅"
    tone="primary">

    <form method="POST" action="{{ $action }}" class="space-y-4" data-entry-form>
        @csrf @method('PUT')

        @if ($errors->any())
            <div class="alert alert-error text-sm">
                <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="form-control">
                <label class="label"><span class="label-text">{{ __('Mitarbeiter') }} *</span></label>
                <select name="user_id" required class="select select-bordered">
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected(old('user_id', $shift->user_id) == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">{{ __('Schichttyp') }}</span></label>
                <select name="shift_type_id" class="select select-bordered">
                    <option value="">—</option>
                    @foreach ($types as $t)
                        <option value="{{ $t->id }}" @selected(old('shift_type_id', $shift->shift_type_id) == $t->id)>{{ $t->name }} ({{ $t->abbreviation }})</option>
                    @endforeach
                </select>
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">{{ __('Datum') }} *</span></label>
                <input type="date" name="date" required
                       value="{{ old('date', $shift->date->format('Y-m-d')) }}"
                       class="input input-bordered">
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">{{ __('Status') }}</span></label>
                <select name="status" class="select select-bordered">
                    @foreach (\App\Models\ScheduledShift::$statuses as $s)
                        <option value="{{ $s }}" @selected(old('status', $shift->status) === $s)>{{ $s }}</option>
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
            <div class="form-control sm:col-span-2">
                <label class="label"><span class="label-text">{{ __('Notiz') }}</span></label>
                <textarea name="note" rows="3" class="textarea textarea-bordered">{{ old('note', $shift->note) }}</textarea>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="btn btn-primary btn-sm">{{ __('Speichern') }}</button>
            <button type="button" class="btn btn-ghost btn-sm" data-entry-modal-close>{{ __('Abbrechen') }}</button>
        </div>
    </form>

    @can('delete', $shift)
        <form method="POST" action="{{ route('scheduled-shifts.destroy', $shift) }}" class="mt-3"
              onsubmit="return confirm('{{ __('Wirklich löschen?') }}')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-error btn-outline btn-sm">{{ __('Schicht löschen') }}</button>
        </form>
    @endcan
</x-dialog>
