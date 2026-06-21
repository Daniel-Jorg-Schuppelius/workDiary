{{-- Variablen: $room, $isEdit, $customers, $sites, $buildings, $floors, $cleaningProfiles, $usageTypes --}}
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

    // Verortung aus dem Raum + ggf. der Floor-Kette ableiten, damit der Picker vorbelegt ist.
    $floorRel = $room?->floorRelation;
    $buildingRel = $floorRel?->building;
    $siteRel = $buildingRel?->site;
    $prefill ??= [];
    $pickerSelected = [
        'customer_id' => $room?->customer_id ?? ($prefill['customer_id'] ?? null),
        'site_id'     => $siteRel?->id ?? ($prefill['site_id'] ?? null),
        'building_id' => $buildingRel?->id ?? ($prefill['building_id'] ?? null),
        'floor_id'    => $room?->floor_id ?? ($prefill['floor_id'] ?? null),
    ];
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
                   class="input input-bordered w-full font-mono @error('code') input-error @enderror"
                   value="{{ old('code', $room?->code) }}">
            @error('code')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="room-capacity">{{ __('Kapazität') }}</label>
            <input id="room-capacity" type="number" min="1" name="capacity"
                   class="input input-bordered w-full @error('capacity') input-error @enderror"
                   value="{{ old('capacity', $room?->capacity) }}">
            @error('capacity')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="room-usage">{{ __('Nutzung') }}</label>
            <select id="room-usage" name="usage_type"
                    class="select select-bordered w-full @error('usage_type') select-error @enderror">
                @foreach ($usageTypes as $value => $label)
                    <option value="{{ $value }}" @selected(old('usage_type', $room?->usage_type?->value ?? \App\Enums\Facility\RoomUsageType::Office->value) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('usage_type')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    <x-form-group :legend="__('Verortung')" icon="location_on" tone="info" cols="2">
        <x-facility-picker
            :customers="$customers"
            :sites="$sites"
            :buildings="$buildings"
            :floors="$floors"
            :selected="$pickerSelected"
            require-floor />
    </x-form-group>

    @php
        $hasCleaningProfiles = $cleaningProfiles->isNotEmpty();
        $kennzahlenLegend = $hasCleaningProfiles ? __('Reinigung & Kennzahlen') : __('Kennzahlen');
        $kennzahlenIcon = $hasCleaningProfiles ? 'cleaning_services' : 'straighten';
    @endphp
    <x-form-group :legend="$kennzahlenLegend" :icon="$kennzahlenIcon" tone="success" cols="2">
        @if ($hasCleaningProfiles)
            <div class="fieldset">
                <label class="fieldset-label" for="room-cleaning-profile">{{ __('Reinigungsprofil') }}</label>
                <select id="room-cleaning-profile" name="cleaning_profile_id"
                        class="select select-bordered w-full @error('cleaning_profile_id') select-error @enderror">
                    <option value="">{{ __('— ohne Profil —') }}</option>
                    @foreach ($cleaningProfiles as $profile)
                        <option value="{{ $profile->sqid }}" @selected((string) old('cleaning_profile_id', \App\Support\Sqid::encode(\App\Models\CleaningProfile::class, $room?->cleaning_profile_id)) === $profile->sqid)>{{ $profile->label }}@if ($profile->code) ({{ $profile->code }})@endif</option>
                    @endforeach
                </select>
                @error('cleaning_profile_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
            </div>
        @elseif ($room?->cleaning_profile_id)
            {{-- Bestehende Zuordnung beibehalten, falls Profile später entfernt wurden. --}}
            <input type="hidden" name="cleaning_profile_id" value="{{ $room->cleaning_profile_id }}">
        @endif

        <div class="fieldset @unless ($hasCleaningProfiles) md:col-span-2 @endunless">
            <label class="fieldset-label" for="room-area">{{ __('Nettogrundfläche (m²)') }}</label>
            <input id="room-area" type="number" step="0.01" min="0" name="net_area_m2"
                   class="input input-bordered w-full @error('net_area_m2') input-error @enderror"
                   value="{{ old('net_area_m2', $room?->net_area_m2) }}">
            @error('net_area_m2')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    <x-form-group :legend="__('Ausstattung')" icon="checklist" tone="ghost">
        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
            @foreach ($available as $eq)
                <label class="label cursor-pointer justify-start gap-2">
                    <input type="checkbox" name="equipment[]" value="{{ $eq }}"
                           class="checkbox checkbox-sm"
                           @checked(in_array($eq, (array) $equipment, true))>
                    <span class="label-text">{{ __("values.$eq") }}</span>
                </label>
            @endforeach
        </div>

        <div class="fieldset mt-3">
            <label class="fieldset-label" for="room-notes">{{ __('Notizen') }}</label>
            <textarea id="room-notes" name="notes" rows="3"
                      class="textarea textarea-bordered w-full">{{ old('notes', $room?->notes) }}</textarea>
        </div>
    </x-form-group>

    @if ($isEdit && isset($requirementKinds))
        {{-- Raumbezogene Anforderungen je Gewerk (Feature 027): eigene 1:n-Sätze,
             unabhängig vom Reinigungsprofil. Werden über separate Routen gepflegt;
             daher hier nur Anzeige + Inline-Add (eigene Formulare ausserhalb der
             Modal-Form, damit kein verschachteltes <form> entsteht). --}}
        <x-form-group :legend="__('Raumanforderungen')" icon="rule" tone="warning">
            <p class="text-sm text-base-content/60">{{ __('Fachliche Anforderungen je Gewerk – ergänzend zum Reinigungsprofil.') }}</p>
            @if ($room->requirements->isNotEmpty())
                <ul class="mt-2 space-y-1">
                    @foreach ($room->requirements as $req)
                        <li class="flex flex-wrap items-center gap-2 rounded-box border border-base-300 p-2 text-sm">
                            <x-icon :name="$req->kind->icon()" class="text-warning" />
                            <span class="font-medium">{{ $req->kind->label() }}</span>
                            @if ($req->level)<span class="badge badge-sm badge-outline">{{ $req->level }}</span>@endif
                            @unless ($req->is_active)<span class="badge badge-sm badge-ghost">{{ __('inaktiv') }}</span>@endunless
                            @if ($req->note)<span class="text-base-content/60">— {{ $req->note }}</span>@endif
                            <x-button tone="ghost" size="xs" class="ml-auto text-error"
                               href="#"
                               onclick="event.preventDefault(); document.getElementById('req-del-{{ $req->id }}').submit();">{{ __('Entfernen') }}</x-button>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-2 text-sm text-base-content/50">{{ __('Noch keine Anforderungen hinterlegt.') }}</p>
            @endif
        </x-form-group>
    @endif

    @if ($errors->any())
        <div class="alert alert-error text-sm">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
</x-modal>

@if ($isEdit && isset($requirementKinds))
    {{-- Hilfs-Formulare ausserhalb der Modal-Form (keine verschachtelten Forms). --}}
    @foreach ($room->requirements as $req)
        <form id="req-del-{{ $req->id }}" method="POST"
              action="{{ route('rooms.requirements.destroy', [$room, $req]) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
    <form method="POST" action="{{ route('rooms.requirements.store', $room) }}"
          class="mt-3 flex flex-wrap items-end gap-2 rounded-box border border-base-300 p-3">
        @csrf
        <div class="fieldset">
            <label class="fieldset-label" for="req-kind">{{ __('Anforderung') }}</label>
            <select id="req-kind" name="kind" class="select select-bordered select-sm">
                @foreach ($requirementKinds as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="fieldset">
            <label class="fieldset-label" for="req-level">{{ __('Stufe / Wert') }}</label>
            <input id="req-level" type="text" name="level" maxlength="60" class="input input-bordered input-sm">
        </div>
        <div class="fieldset grow">
            <label class="fieldset-label" for="req-note">{{ __('Notiz') }}</label>
            <input id="req-note" type="text" name="note" maxlength="2000" class="input input-bordered input-sm w-full">
        </div>
        <x-button type="submit" tone="warning" size="sm">{{ __('Hinzufügen') }}</x-button>
    </form>
@endif
