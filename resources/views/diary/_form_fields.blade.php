{{-- Inhalt der Diary Form. Erwartet: $entry, $isEdit, $allTags, $selectedTagIds --}}
@php
    $canCreateForOthers = $canCreateForOthers ?? false;
    $assignableUsers = $assignableUsers ?? collect();
    $prefillStartAt = $prefillStartAt ?? null;
    $prefillUserId = $prefillUserId ?? 0;
    $defaultUserId = old('user_id', $entry?->user_id ?? ($prefillUserId ?: auth()->id()));

    // Phase 6: EntryType-gesteuerte Felder
    $entryTypes = $entryTypes ?? collect();
    $entryTypeFlags = $entryTypeFlags ?? [];
    $customerOptions = $customerOptions ?? collect();
    $tourOptions = $tourOptions ?? collect();
    $defaultEntryTypeId = old('entry_type_id', $entry?->entry_type_id ?? ($prefillEntryTypeId ?? 0));
    // Initialer Flags-Block für Alpine (auch wenn nichts gewählt ist).
    $initialFlags = $entryTypeFlags[(int) $defaultEntryTypeId] ?? [
        'requires_customer' => false,
        'requires_address' => false,
        'requires_schedule' => false,
        'requires_tour' => false,
        'allow_priority' => false,
        'allow_tour' => false,
        'default_service_minutes' => null,
        'default_priority' => null,
        'default_status' => 2,
    ];

    $defaultMode = old('mode', $entry?->mode?->value ?? \App\Enums\Diary\Mode::Fixed->value);
    if ($defaultMode === \App\Enums\Diary\Mode::Recurring->value) {
        // 'recurring' wird vom Generator gesetzt — Auswahl bietet stattdessen
        // den passenden Bearbeitungs-Modus an, ohne den Datensatz zu zerstören.
        $defaultMode = \App\Enums\Diary\Mode::Fixed->value;
    }
    $defaultLocation = old('location_mode', $entry?->location_mode?->value ?? \App\Enums\Diary\LocationMode::Onsite->value);
@endphp

<div
    x-data="{
        entryTypeId: @js((int) $defaultEntryTypeId),
        flagsMap: @js($entryTypeFlags),
        flags: @js($initialFlags),
        mode: @js($defaultMode),
        onTypeChange() {
            const id = parseInt(this.entryTypeId || 0, 10);
            const next = this.flagsMap[id] ?? {
                requires_customer: false, requires_address: false, requires_schedule: false,
                requires_tour: false, allow_priority: false, allow_tour: false,
                default_service_minutes: null, default_priority: null, default_status: 2,
            };
            this.flags = next;
        },
    }"
    class="space-y-4"
>

@if (! $isEdit && $canCreateForOthers && $assignableUsers->isNotEmpty())
    <x-form-group :legend="__('Zuordnung')" icon="person" tone="primary">
        <div class="fieldset">
            <label class="fieldset-label" for="user_id">{{ __('Benutzer') }} *</label>
            <select
                id="user_id"
                name="user_id"
                class="select select-bordered w-full @error('user_id') select-error @enderror"
            >
                @foreach ($assignableUsers as $u)
                    <option value="{{ $u->sqid }}" @selected((string) old('user_id', \App\Support\Sqid::encode(\App\Models\User::class, $defaultUserId)) === $u->sqid)>{{ $u->name }}</option>
                @endforeach
            </select>
            @error('user_id')
                <p class="text-error text-sm">{{ $message }}</p>
            @enderror
        </div>
    </x-form-group>
@endif

@if ($entryTypes->isNotEmpty())
    <x-form-group :legend="__('Typ')" icon="category" tone="primary" cols="2">
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label" for="entry_type_id">{{ __('Eintragstyp') }}</label>
            <select
                id="entry_type_id"
                name="entry_type_id"
                x-model.number="entryTypeId"
                @change="onTypeChange()"
                class="select select-bordered w-full @error('entry_type_id') select-error @enderror"
            >
                <option value="0">{{ __('— ohne Typ —') }}</option>
                @foreach ($entryTypes as $type)
                    <option value="{{ $type->sqid }}" @selected((string) old('entry_type_id', \App\Support\Sqid::encode(\App\Models\EntryType::class, $defaultEntryTypeId)) === $type->sqid)>
                        {{ $type->label }}
                    </option>
                @endforeach
            </select>
            @error('entry_type_id')
                <p class="text-error text-sm">{{ $message }}</p>
            @enderror
        </div>

        <div class="fieldset" x-show="entryTypeId > 0" x-cloak>
            <label class="fieldset-label" for="title">{{ __('Titel') }}</label>
            <input
                type="text"
                id="title"
                name="title"
                maxlength="200"
                value="{{ old('title', $entry?->title) }}"
                class="input input-bordered w-full @error('title') input-error @enderror"
                placeholder="{{ __('Kurze Bezeichnung des Auftrags') }}"
            >
            @error('title')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset" x-show="flags.allow_priority" x-cloak>
            <label class="fieldset-label" for="priority">{{ __('Priorität') }}</label>
            <select id="priority" name="priority" class="select select-bordered w-full @error('priority') select-error @enderror">
                <option value="">—</option>
                @foreach (\App\Enums\Diary\Priority::cases() as $p)
                    <option value="{{ $p->value }}" @selected(old('priority', $entry?->priority?->value) === $p->value)>{{ $p->label() }}</option>
                @endforeach
            </select>
            @error('priority')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
    </x-form-group>
@else
    <input type="hidden" name="entry_type_id" value="0">
@endif

<x-form-group :legend="__('Eintrag')" icon="edit" tone="primary">
    <div class="fieldset">
        <label class="fieldset-label" for="content">{{ __('Inhalt') }} *</label>
        <textarea
            id="content"
            name="content"
            rows="8"
            class="textarea textarea-bordered w-full @error('content') textarea-error @enderror"
            placeholder="{{ __('Beschreibe den Vorgang...') }}"
        >{{ old('content', $entry?->content) }}</textarea>
        @error('content')
            <p class="text-error text-sm">{{ $message }}</p>
        @enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label" for="response">{{ __('Rückmeldung') }}</label>
        <textarea
            id="response"
            name="response"
            rows="4"
            class="textarea textarea-bordered w-full"
            placeholder="{{ __('Antwort oder Notiz (optional) ...') }}"
        >{{ old('response', $entry?->response) }}</textarea>
    </div>
</x-form-group>

<x-form-group :legend="__('Status & Zeitraum')" icon="traffic" tone="info" cols="2">
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label" for="status">{{ __('Status') }} *</label>
        <select
            id="status"
            name="status"
            class="select select-bordered w-full @error('status') select-error @enderror"
        >
            <option value="2" @selected((int) old('status', $entry?->status?->value ?? 2) === 2)>{{ __('Offen') }}</option>
            <option value="3" @selected((int) old('status', $entry?->status?->value) === 3)>{{ __('Problem') }}</option>
            <option value="1" @selected((int) old('status', $entry?->status?->value) === 1)>{{ __('Bestätigt') }}</option>
            <option value="-1" @selected((int) old('status', $entry?->status?->value) === -1)>{{ __('Erledigt') }}</option>
        </select>
        @error('status')
            <p class="text-error text-sm">{{ $message }}</p>
        @enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label" for="mode">{{ __('Termin-Modus') }} *</label>
        <select
            id="mode"
            name="mode"
            x-model="mode"
            class="select select-bordered w-full @error('mode') select-error @enderror"
        >
            <option value="{{ \App\Enums\Diary\Mode::Fixed->value }}">{{ __('Terminiert (fester Zeitraum)') }}</option>
            <option value="{{ \App\Enums\Diary\Mode::Deadline->value }}">{{ __('Deadline (bis Datum X)') }}</option>
            <option value="{{ \App\Enums\Diary\Mode::Window->value }}">{{ __('Zeitfenster (Korridor)') }}</option>
            <option value="{{ \App\Enums\Diary\Mode::Backlog->value }}">{{ __('Backlog (irgendwann)') }}</option>
        </select>
        @error('mode')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label" for="location_mode">{{ __('Standort') }} *</label>
        <select
            id="location_mode"
            name="location_mode"
            class="select select-bordered w-full @error('location_mode') select-error @enderror"
        >
            @foreach (\App\Enums\Diary\LocationMode::cases() as $lm)
                <option value="{{ $lm->value }}" @selected($defaultLocation === $lm->value)>{{ $lm->label() }}</option>
            @endforeach
        </select>
        @error('location_mode')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    {{-- Fester Zeitraum --}}
    <div class="fieldset md:col-span-2" x-show="mode === '{{ \App\Enums\Diary\Mode::Fixed->value }}'" x-cloak>
        <label class="fieldset-label">{{ __('Zeitraum') }}</label>
        <x-date-range
            type="datetime-local"
            fromName="start_at"
            toName="end_at"
            fromId="start_at"
            toId="end_at"
            :from="old('start_at', $entry?->start_at?->format('Y-m-d\TH:i') ?? $prefillStartAt)"
            :to="old('end_at', $entry?->end_at?->format('Y-m-d\TH:i'))"
            :label="false"
            class="w-full"
        />
        @error('start_at')<p class="text-error text-sm">{{ $message }}</p>@enderror
        @error('end_at')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    {{-- Deadline --}}
    <div class="fieldset md:col-span-2" x-show="mode === '{{ \App\Enums\Diary\Mode::Deadline->value }}'" x-cloak>
        <label class="fieldset-label" for="due_date">{{ __('Fällig bis') }}</label>
        <input
            type="date"
            id="due_date"
            name="due_date"
            value="{{ old('due_date', $entry?->due_date?->format('Y-m-d')) }}"
            class="input input-bordered w-full @error('due_date') input-error @enderror"
        >
        @error('due_date')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    {{-- Zeitfenster --}}
    <div class="fieldset md:col-span-2" x-show="mode === '{{ \App\Enums\Diary\Mode::Window->value }}'" x-cloak>
        <label class="fieldset-label">{{ __('Zeitfenster (Datum von/bis)') }}</label>
        <x-date-range
            type="date"
            fromName="window_start_date"
            toName="window_end_date"
            fromId="window_start_date"
            toId="window_end_date"
            :from="old('window_start_date', $entry?->window_start_date?->format('Y-m-d'))"
            :to="old('window_end_date', $entry?->window_end_date?->format('Y-m-d'))"
            :label="false"
            class="w-full"
        />
        @error('window_start_date')<p class="text-error text-sm">{{ $message }}</p>@enderror
        @error('window_end_date')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    {{-- Backlog: keine Datumsfelder --}}
    <div class="fieldset md:col-span-2" x-show="mode === '{{ \App\Enums\Diary\Mode::Backlog->value }}'" x-cloak>
        <p class="text-sm text-base-content/60">{{ __('Kein Datum erfasst — erscheint im Backlog und kann später terminiert werden.') }}</p>
    </div>
</x-form-group>

{{-- Kunde / zugewiesener Benutzer (typabhängig) --}}
<template x-if="flags.requires_customer">
    <x-form-group :legend="__('Kunde & Zuweisung')" icon="badge" tone="secondary" cols="2">
        <div class="fieldset">
            <label class="fieldset-label" for="customer_id">{{ __('Kunde') }}</label>
            <select
                id="customer_id"
                name="customer_id"
                class="select select-bordered w-full @error('customer_id') select-error @enderror"
            >
                <option value="">—</option>
                @foreach ($customerOptions as $c)
                    <option value="{{ $c->sqid }}" @selected((string) old('customer_id', \App\Support\Sqid::encode(\App\Models\Customer::class, $entry?->customer_id)) === $c->sqid)>
                        {{ $c->name }}@if ($c->company) — {{ $c->company }}@endif
                    </option>
                @endforeach
            </select>
            @error('customer_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="assigned_user_id">{{ __('Zuständig') }}</label>
            <select
                id="assigned_user_id"
                name="assigned_user_id"
                class="select select-bordered w-full @error('assigned_user_id') select-error @enderror"
            >
                <option value="">—</option>
                @foreach ($assignableUsers as $u)
                    <option value="{{ $u->sqid }}" @selected((string) old('assigned_user_id', \App\Support\Sqid::encode(\App\Models\User::class, $entry?->assigned_user_id)) === $u->sqid)>{{ $u->name }}</option>
                @endforeach
            </select>
            @error('assigned_user_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
    </x-form-group>
</template>

{{-- Termin / Zeitfenster / Servicedauer --}}
<template x-if="flags.requires_schedule">
    <x-form-group :legend="__('Termin & Servicezeit')" icon="event" tone="info" cols="3">
        <div class="fieldset">
            <label class="fieldset-label" for="scheduled_for">{{ __('Datum') }}</label>
            <input
                type="date"
                id="scheduled_for"
                name="scheduled_for"
                value="{{ old('scheduled_for', optional($entry?->scheduled_for)->format('Y-m-d')) }}"
                class="input input-bordered w-full @error('scheduled_for') input-error @enderror"
            >
            @error('scheduled_for')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="time_window_start">{{ __('Zeitfenster ab') }}</label>
            <input
                type="time"
                id="time_window_start"
                name="time_window_start"
                value="{{ old('time_window_start', $entry?->time_window_start) }}"
                class="input input-bordered w-full @error('time_window_start') input-error @enderror"
            >
            @error('time_window_start')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="time_window_end">{{ __('Zeitfenster bis') }}</label>
            <input
                type="time"
                id="time_window_end"
                name="time_window_end"
                value="{{ old('time_window_end', $entry?->time_window_end) }}"
                class="input input-bordered w-full @error('time_window_end') input-error @enderror"
            >
            @error('time_window_end')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset md:col-span-3">
            <label class="fieldset-label" for="service_minutes">{{ __('Servicedauer (Minuten)') }}</label>
            <input
                type="number"
                min="0"
                step="5"
                id="service_minutes"
                name="service_minutes"
                :placeholder="flags.default_service_minutes ?? ''"
                value="{{ old('service_minutes', $entry?->service_minutes) }}"
                class="input input-bordered w-full @error('service_minutes') input-error @enderror"
            >
            @error('service_minutes')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
    </x-form-group>
</template>

{{-- Adresse --}}
<template x-if="flags.requires_address">
    <x-form-group :legend="__('Adresse')" icon="location_on" tone="warning" cols="2">
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label" for="address_line">{{ __('Straße & Nummer') }}</label>
            <input type="text" id="address_line" name="address_line" maxlength="200"
                value="{{ old('address_line', $entry?->address_line) }}"
                class="input input-bordered w-full @error('address_line') input-error @enderror">
            @error('address_line')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="address_zip">{{ __('PLZ') }}</label>
            <input type="text" id="address_zip" name="address_zip" maxlength="16"
                value="{{ old('address_zip', $entry?->address_zip) }}"
                class="input input-bordered w-full @error('address_zip') input-error @enderror">
            @error('address_zip')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="address_city">{{ __('Stadt') }}</label>
            <input type="text" id="address_city" name="address_city" maxlength="120"
                value="{{ old('address_city', $entry?->address_city) }}"
                class="input input-bordered w-full @error('address_city') input-error @enderror">
            @error('address_city')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="address_country">{{ __('Land (ISO-2)') }}</label>
            <input type="text" id="address_country" name="address_country" maxlength="2"
                value="{{ old('address_country', $entry?->address_country) }}"
                class="input input-bordered w-full uppercase @error('address_country') input-error @enderror">
            @error('address_country')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="address_lat">{{ __('Lat') }}</label>
            <input type="number" step="0.0000001" id="address_lat" name="address_lat"
                value="{{ old('address_lat', $entry?->address_lat) }}"
                class="input input-bordered w-full @error('address_lat') input-error @enderror">
            @error('address_lat')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="address_lng">{{ __('Lng') }}</label>
            <input type="number" step="0.0000001" id="address_lng" name="address_lng"
                value="{{ old('address_lng', $entry?->address_lng) }}"
                class="input input-bordered w-full @error('address_lng') input-error @enderror">
            @error('address_lng')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
    </x-form-group>
</template>

{{-- Tour-Zuordnung --}}
<template x-if="flags.allow_tour">
    <x-form-group :legend="__('Tour')" icon="route" tone="accent" cols="2">
        <div class="fieldset">
            <label class="fieldset-label" for="tour_id">{{ __('Tour') }}</label>
            <select id="tour_id" name="tour_id" class="select select-bordered w-full @error('tour_id') select-error @enderror">
                <option value="">—</option>
                @foreach ($tourOptions as $t)
                    <option value="{{ $t->sqid }}" @selected((string) old('tour_id', \App\Support\Sqid::encode(\App\Models\Tour::class, $entry?->tour_id)) === $t->sqid)>
                        {{ optional($t->tour_date)->format('Y-m-d') }} · {{ $t->name ?? '#'.$t->id }}
                    </option>
                @endforeach
            </select>
            @error('tour_id')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="tour_position">{{ __('Position') }}</label>
            <input type="number" min="0" id="tour_position" name="tour_position"
                value="{{ old('tour_position', $entry?->tour_position) }}"
                class="input input-bordered w-full @error('tour_position') input-error @enderror">
            @error('tour_position')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
    </x-form-group>
</template>

<x-form-group :legend="__('Tags')" icon="flag" tone="success">
    <div class="fieldset">
        <?php $currentTagIds = old('tag_ids', $selectedTagIds ?? []); ?>
        @if (($allTags ?? collect())->isNotEmpty())
            <div class="flex flex-wrap gap-2 mb-3">
                @foreach ($allTags as $tag)
                    <label class="cursor-pointer">
                        <input type="checkbox" name="tag_ids[]" value="{{ $tag->sqid }}"
                            @checked(in_array((string) $tag->id, array_map('strval', (array) $currentTagIds), true))
                            class="peer sr-only">
                        <span class="badge badge-outline peer-checked:badge-primary peer-checked:text-primary-content"
                            @if ($tag->color) style="border-color: {{ $tag->color }};" @endif>
                            {{ $tag->name }}
                        </span>
                    </label>
                @endforeach
            </div>
        @endif
        <input type="text" name="new_tags" value="{{ old('new_tags') }}"
            class="input input-bordered input-sm w-full"
            placeholder="{{ __('Neue Tags durch Komma getrennt') }}">
    </div>
</x-form-group>

</div>
