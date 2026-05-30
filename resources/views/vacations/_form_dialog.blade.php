{{-- Variablen: $vacation, $isEdit, $isDialog, $canAssignOthers, $assignableUsers, $prefillStart, $prefillEnd --}}
@php
    $isEdit   = $isEdit   ?? false;
    $isDialog = $isDialog ?? true;
    $action   = $isEdit ? route('vacations.update', $vacation) : route('vacations.store');
    $dialogUrl = ($isEdit ? route('vacations.edit', $vacation) : route('vacations.create')) . '?dialog=1';

    $selectedUser = (string) old('user_id', \App\Support\Sqid::encode(\App\Models\User::class, $vacation?->user_id ?? auth()->id()));
    $typeOptions  = \App\Enums\Vacation\VacationType::options();
    $defaultType  = \App\Enums\Vacation\VacationType::Vacation->value;
@endphp

<x-modal
    :title="$isEdit ? __('Urlaubsantrag bearbeiten') : __('Urlaubsantrag stellen')"
    :eyebrow="__('Urlaubsverwaltung')"
    icon="beach_access"
    tone="success"
    :action="$action"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Antrag stellen')">

    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    @if ($canAssignOthers && $assignableUsers->isNotEmpty())
        <x-form-group :legend="__('Zuordnung')" icon="person" tone="primary">
                <div class="fieldset">
                    <label class="fieldset-label" for="vac-user">{{ __('Mitarbeiter') }}</label>
                    <select id="vac-user" name="user_id" class="select select-bordered w-full">
                        @foreach ($assignableUsers as $u)
                            @php($uid = (int) ($u['id'] ?? $u->id))
                            @php($usqid = \App\Support\Sqid::encode(\App\Models\User::class, $uid))
                            <option value="{{ $usqid }}" @selected($selectedUser === $usqid)>{{ $u['name'] ?? $u->name }}</option>
                        @endforeach
                    </select>
                </div>
            </x-form-group>
        @endif

    <x-form-group :legend="__('Antrag')" icon="beach_access" tone="success">
            <div class="fieldset">
                <label class="fieldset-label" for="vac-type">{{ __('Typ') }}</label>
                <select id="vac-type" name="type" class="select select-bordered w-full">
                    @foreach ($typeOptions as $val => $label)
                        <option value="{{ $val }}" @selected(old('type', $vacation?->type?->value ?? $defaultType) === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="fieldset">
                <label class="fieldset-label">{{ __('Zeitraum') }} *</label>
                <x-date-range
                    type="date"
                    :from="old('start_date', $vacation?->start_date?->format('Y-m-d') ?? $prefillStart)"
                    :to="old('end_date',   $vacation?->end_date?->format('Y-m-d')   ?? $prefillEnd)"
                    fromName="start_date"
                    toName="end_date"
                    :fromLabel="__('Von')"
                    :toLabel="__('Bis')"
                    :label="false"
                    required
                    class="w-full"
                />
                @error('start_date')<p class="text-error text-sm">{{ $message }}</p>@enderror
                @error('end_date')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>
        </x-form-group>

    <x-form-group :legend="__('Begründung')" icon="description" tone="ghost">
            <div class="fieldset">
                <label class="fieldset-label" for="vac-note">{{ __('Notiz') }}</label>
                <textarea id="vac-note" name="note" rows="3" class="textarea textarea-bordered w-full">{{ old('note', $vacation?->note) }}</textarea>
            </div>
        </x-form-group>

    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
</x-modal>
