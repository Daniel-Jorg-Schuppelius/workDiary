{{-- Variablen: $vacation, $isEdit, $isDialog, $canAssignOthers, $assignableUsers, $prefillStart, $prefillEnd --}}
@php
    $isEdit   = $isEdit   ?? false;
    $isDialog = $isDialog ?? true;
    $action   = $isEdit ? route('vacations.update', $vacation) : route('vacations.store');
    $dialogUrl = ($isEdit ? route('vacations.edit', $vacation) : route('vacations.create')) . '?dialog=1';

    $selectedUser = (int) old('user_id', $vacation?->user_id ?? auth()->id());
    $typeOptions  = [
        \App\Models\Vacation::TYPE_VACATION => __('Urlaub'),
        \App\Models\Vacation::TYPE_SICK     => __('Krank'),
        \App\Models\Vacation::TYPE_SPECIAL  => __('Sonderurlaub'),
        \App\Models\Vacation::TYPE_UNPAID   => __('Unbezahlt'),
    ];
@endphp

<x-dialog
    :title="$isEdit ? __('Urlaubsantrag bearbeiten') : __('Urlaubsantrag stellen')"
    :eyebrow="__('Urlaubsverwaltung')"
    icon="🏖️"
    tone="success">

    <form method="POST" action="{{ $action }}" class="space-y-4" data-entry-form>
        @csrf
        @if ($isEdit) @method('PUT') @endif
        @if ($isDialog)
            <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
        @endif

        @if ($canAssignOthers && $assignableUsers->isNotEmpty())
            <div>
                <label class="label" for="vac-user"><span class="label-text">{{ __('Mitarbeiter') }}</span></label>
                <select id="vac-user" name="user_id" class="select select-bordered w-full">
                    @foreach ($assignableUsers as $u)
                        <option value="{{ $u['id'] ?? $u->id }}" @selected($selectedUser === (int) ($u['id'] ?? $u->id))>{{ $u['name'] ?? $u->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <label class="label" for="vac-type"><span class="label-text">{{ __('Typ') }}</span></label>
            <select id="vac-type" name="type" class="select select-bordered w-full">
                @foreach ($typeOptions as $val => $label)
                    <option value="{{ $val }}" @selected(old('type', $vacation?->type ?? \App\Models\Vacation::TYPE_VACATION) === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <x-date-range
            layout="split"
            type="date"
            :from="old('start_date', $vacation?->start_date?->format('Y-m-d') ?? $prefillStart)"
            :to="old('end_date',   $vacation?->end_date?->format('Y-m-d')   ?? $prefillEnd)"
            fromName="start_date"
            toName="end_date"
            :fromLabel="__('Von')"
            :toLabel="__('Bis')"
            size=""
            formControl
            required
            gridClass="grid grid-cols-1 gap-4 sm:grid-cols-2"
            :fromError="$errors->first('start_date')"
            :toError="$errors->first('end_date')"
        />

        <div>
            <label class="label" for="vac-note"><span class="label-text">{{ __('Notiz') }}</span></label>
            <textarea id="vac-note" name="note" rows="3" class="textarea textarea-bordered w-full">{{ old('note', $vacation?->note) }}</textarea>
        </div>

        @if ($errors->any())
            <div class="alert alert-error text-sm">
                <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="btn btn-sm btn-primary">{{ $isEdit ? __('Speichern') : __('Antrag stellen') }}</button>
            <button type="button" class="btn btn-sm btn-ghost" data-entry-modal-close>{{ __('Abbrechen') }}</button>
        </div>
    </form>
</x-dialog>
