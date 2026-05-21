{{-- Variablen: $room, $isEdit --}}
@php
    /** @var \App\Models\Room|null $room */
    /** @var bool $isEdit */
    $isEdit ??= false;
    $action  = $isEdit ? route('rooms.update', $room) : route('rooms.store');
    $method  = $isEdit ? 'PUT' : 'POST';
    $title   = $isEdit ? __('Raum bearbeiten') : __('Neuer Raum');
    $dialogUrl = ($isEdit ? route('rooms.edit', $room) : route('rooms.create')).'?dialog=1';

    $equipment = old('equipment', $room?->equipment ?? []);
    $available = ['beamer', 'whiteboard', 'video_conf', 'flipchart', 'audio', 'wlan'];
@endphp

<x-modal
    :title="$title"
    :eyebrow="__('Räume')"
    icon="meeting_room"
    tone="primary"
    size="lg"
    :action="$action"
    :method="$method"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    <x-slot:headerActions>
        <x-dialog-status-controls
            :active="$room?->is_active ?? true"
            :color="$room?->color ?? '#3b82f6'" />
    </x-slot:headerActions>

    <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">

    <x-form-group :legend="__('Stammdaten')" icon="meeting_room" tone="primary" cols="2">
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label" for="room-name">{{ __('Name') }} *</label>
            <input id="room-name" type="text" name="name" required
                   class="input input-bordered w-full @error('name') input-error @enderror"
                   value="{{ old('name', $room?->name) }}">
            @error('name')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="room-code">{{ __('Code') }}</label>
            <input id="room-code" type="text" name="code"
                   class="input input-bordered w-full font-mono"
                   value="{{ old('code', $room?->code) }}">
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="room-capacity">{{ __('Kapazität') }}</label>
            <input id="room-capacity" type="number" min="1" name="capacity"
                   class="input input-bordered w-full"
                   value="{{ old('capacity', $room?->capacity) }}">
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="room-building">{{ __('Gebäude') }}</label>
            <input id="room-building" type="text" name="building"
                   class="input input-bordered w-full"
                   value="{{ old('building', $room?->building) }}">
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="room-floor">{{ __('Etage') }}</label>
            <input id="room-floor" type="text" name="floor"
                   class="input input-bordered w-full"
                   value="{{ old('floor', $room?->floor) }}">
        </div>

    </x-form-group>

    <x-form-group :legend="__('Ausstattung')" icon="checklist" tone="ghost">
        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
            @foreach ($available as $eq)
                <label class="label cursor-pointer justify-start gap-2">
                    <input type="checkbox" name="equipment[]" value="{{ $eq }}"
                           class="checkbox checkbox-sm"
                           @checked(in_array($eq, (array) $equipment, true))>
                    <span class="label-text">{{ __($eq) }}</span>
                </label>
            @endforeach
        </div>

        <div class="fieldset mt-3">
            <label class="fieldset-label" for="room-notes">{{ __('Notizen') }}</label>
            <textarea id="room-notes" name="notes" rows="3"
                      class="textarea textarea-bordered w-full">{{ old('notes', $room?->notes) }}</textarea>
        </div>
    </x-form-group>

    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
</x-modal>
