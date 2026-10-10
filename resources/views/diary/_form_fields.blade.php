{{--
  Created on   : Sun May 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_fields.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Inhalt der Diary Form. Erwartet: $entry, $isEdit, $allTags, $selectedTagIds --}}
@php
    $canCreateForOthers = $canCreateForOthers ?? false;
    $assignableUsers = $assignableUsers ?? collect();
    $prefillStartAt = $prefillStartAt ?? null;
    $prefillUserId = $prefillUserId ?? 0;
    // Fachliche Prefills (Feature 139: Folgeauftrag aus offenem Punkt).
    $prefillCustomerId = $prefillCustomerId ?? null;
    $prefillProjectId = $prefillProjectId ?? null;
    $prefillTitle = $prefillTitle ?? '';
    $prefillContent = $prefillContent ?? '';
    $prefillOpenIssueSqid = $prefillOpenIssueSqid ?? null;
    $prefillDueDate = $prefillDueDate ?? null;
    $defaultUserId = old('user_id', $entry?->user_id ?? ($prefillUserId ?: auth()->id()));

    // Phase 6: EntryType-gesteuerte Felder
    $entryTypes = $entryTypes ?? collect();
    $entryTypeFlags = $entryTypeFlags ?? [];
    $customerOptions = $customerOptions ?? collect();
    $tourOptions = $tourOptions ?? collect();
    $rawDefaultEntryTypeId = old('entry_type_id', $entry?->entry_type_id ?? ($prefillEntryTypeId ?? 0));
    $defaultEntryTypeSqid = '0';
    if (is_numeric($rawDefaultEntryTypeId) && (int) $rawDefaultEntryTypeId > 0) {
        $defaultEntryTypeSqid = \App\Support\Sqid::encode(\App\Models\Classification\EntryType::class, (int) $rawDefaultEntryTypeId);
    } elseif (is_string($rawDefaultEntryTypeId) && $rawDefaultEntryTypeId !== '' && $rawDefaultEntryTypeId !== '0') {
        $defaultEntryTypeSqid = $rawDefaultEntryTypeId;
    }
    // Initialer Flags-Block für Alpine (auch wenn nichts gewählt ist).
    $initialFlags = $entryTypeFlags[$defaultEntryTypeSqid] ?? [
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
    x-data="diaryEntryForm"
    data-entry-type="{{ $defaultEntryTypeSqid }}"
    data-flags-map='@json($entryTypeFlags)'
    data-flags='@json($initialFlags)'
    data-mode="{{ $defaultMode }}"
    class="space-y-4"
>

@if (! $isEdit && $canCreateForOthers && $assignableUsers->isNotEmpty())
    <x-form-group :legend="__('Zuordnung')" icon="person" tone="primary">
        <x-select-field name="user_id" :label="__('Benutzer')" required>
            @foreach ($assignableUsers as $u)
                <option value="{{ $u->sqid }}" @selected((string) old('user_id', \App\Support\Sqid::encode(\App\Models\Platform\User::class, $defaultUserId)) === $u->sqid)>{{ $u->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>
@endif

@if ($entryTypes->isNotEmpty())
    <x-form-group :legend="__('Typ')" icon="category" tone="primary" cols="2">
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label" for="entry_type_id">{{ __('Eintragstyp') }}</label>
            <select
                id="entry_type_id"
                name="entry_type_id"
                x-model="entryTypeId"
                @change="onTypeChange()"
                class="select select-bordered w-full @error('entry_type_id') select-error @enderror"
            >
                <option value="0">{{ __('— ohne Typ —') }}</option>
                @foreach ($entryTypes as $type)
                    <option value="{{ $type->sqid }}" @selected($defaultEntryTypeSqid === $type->sqid)>
                        {{ $type->label }}
                    </option>
                @endforeach
            </select>
            @error('entry_type_id')
                <p class="text-error text-sm">{{ $message }}</p>
            @enderror
        </div>

        <div class="fieldset" x-show="hasEntryType" x-cloak>
            <label class="fieldset-label" for="title">{{ __('Titel') }}</label>
            <input
                type="text"
                id="title"
                name="title"
                maxlength="200"
                value="{{ old('title', $entry?->title ?? $prefillTitle) }}"
                class="input input-bordered w-full @error('title') input-error @enderror"
                placeholder="{{ __('Kurze Bezeichnung des Auftrags') }}"
            >
            @error('title')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset" x-show="allowPriority" x-cloak>
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
    {{-- MVP-1060: Diktat füllt das Inhaltsfeld als Vorschlag. --}}
    <div class="flex justify-end"><x-dictation-button target="#diary-content" context="diary" /></div>
    <x-textarea-field name="content" id="diary-content" :label="__('Inhalt')" required rows="8" placeholder="{{ __('Beschreiben Sie den Vorgang...') }}" :value="old('content', $entry?->content ?? $prefillContent)" />

    <x-textarea-field name="response" :label="__('Rückmeldung')" rows="4" placeholder="{{ __('Antwort oder Notiz (optional) ...') }}" :value="old('response', $entry?->response)" />
</x-form-group>

<input type="hidden" name="status" value="{{ $entry?->status?->value ?? \App\Enums\Diary\Status::Planned->value }}">
@if (! $isEdit && $prefillOpenIssueSqid !== null)
    {{-- Folgeauftrag (Feature 139): Rückverknüpfung + Projekt aus dem Subjekt des Punkts. --}}
    <input type="hidden" name="open_issue_id" value="{{ old('open_issue_id', $prefillOpenIssueSqid) }}">
    @if ($prefillProjectId !== null)
        <input type="hidden" name="project_id" value="{{ old('project_id', \App\Support\Sqid::encode(\App\Models\Project\Project::class, $prefillProjectId)) }}">
    @endif
@endif

<x-form-group :legend="__('Zeitraum')" icon="event" tone="info" cols="2">
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

    <x-select-field name="location_mode" :label="__('Standort')" required>
        @foreach (\App\Enums\Diary\LocationMode::cases() as $lm)
            <option value="{{ $lm->value }}" @selected($defaultLocation === $lm->value)>{{ $lm->label() }}</option>
        @endforeach
    </x-select-field>

    {{-- Fester Zeitraum --}}
    <div class="fieldset md:col-span-2" x-show="isMode('{{ \App\Enums\Diary\Mode::Fixed->value }}')" x-cloak>
        <span class="fieldset-label">{{ __('Zeitraum') }}</span>
        <x-date-range
            type="datetime-local"
            fromName="start_at"
            toName="end_at"
            fromId="start_at"
            toId="end_at"
            :from="old('start_at', $entry?->start_at?->orgTz()->format('Y-m-d\TH:i') ?? $prefillStartAt)"
            :to="old('end_at', $entry?->end_at?->orgTz()->format('Y-m-d\TH:i'))"
            :label="false"
            class="w-full"
        />
        @error('start_at')<p class="text-error text-sm">{{ $message }}</p>@enderror
        @error('end_at')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    {{-- Deadline --}}
    <div class="fieldset md:col-span-2" x-show="isMode('{{ \App\Enums\Diary\Mode::Deadline->value }}')" x-cloak>
        <label class="fieldset-label" for="due_date">{{ __('Fällig bis') }}</label>
        <input
            type="date"
            id="due_date"
            name="due_date"
            value="{{ old('due_date', $entry?->due_date?->format('Y-m-d') ?? $prefillDueDate) }}"
            class="input input-bordered w-full @error('due_date') input-error @enderror"
        >
        @error('due_date')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    {{-- Zeitfenster --}}
    <div class="fieldset md:col-span-2" x-show="isMode('{{ \App\Enums\Diary\Mode::Window->value }}')" x-cloak>
        <span class="fieldset-label">{{ __('Zeitfenster (Datum von/bis)') }}</span>
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
    <div class="fieldset md:col-span-2" x-show="isMode('{{ \App\Enums\Diary\Mode::Backlog->value }}')" x-cloak>
        {{-- raw-markup-ok: Hinweis am Formularfeld, kein Leerzustand einer Liste --}}
        <p class="text-sm text-muted">{{ __('Kein Datum erfasst — erscheint im Backlog und kann später terminiert werden.') }}</p>
    </div>

    {{-- Geplante Dauer (E13, MVP-1101): Plan für Plan/Ist; leer = aus Zeitfenster bzw. Termin abgeleitet. --}}
    <x-input-field name="planned_duration" :label="__('diary.planned_duration.label')" inputmode="numeric"
                   pattern="^\d{1,3}:[0-5]\d$" placeholder="1:30" :hint="__('diary.planned_duration.hint')"
                   :value="old('planned_duration', $entry?->planned_minutes !== null ? \CommonToolkit\ValueObjects\Duration::ofMinutes($entry->planned_minutes)->toClock() : '')" />
    @error('planned_minutes')<p class="text-error text-sm">{{ $message }}</p>@enderror
</x-form-group>

{{-- Kunde / zugewiesener Benutzer: immer verfügbar (Server erlaubt Kunde
     auch ohne fordernden Typ); Pflicht-Markierung nur typabhängig. Früher
     stand der Block hinter x-if="requiresCustomer" — damit war ein Kunde
     bei nicht-fordernden Typen nie zuweisbar und Bestandswerte unsichtbar. --}}
<x-form-group :legend="__('Kunde & Zuweisung')" icon="badge" tone="secondary" cols="2">
    <x-select-field name="customer_id" :label="__('Kunde')" x-bind:required="requiresCustomer">
        <option value="">—</option>
        @foreach ($customerOptions as $c)
            <option value="{{ $c->sqid }}" @selected((string) old('customer_id', \App\Support\Sqid::encode(\App\Models\Customer\Customer::class, $entry?->customer_id ?? $prefillCustomerId)) === $c->sqid)>
                {{ $c->name }}@if ($c->company) — {{ $c->company }}@endif
            </option>
        @endforeach
    </x-select-field>

    <x-select-field name="assigned_user_id" :label="__('Zuständig')">
        <option value="">—</option>
        @foreach ($assignableUsers as $u)
            <option value="{{ $u->sqid }}" @selected((string) old('assigned_user_id', \App\Support\Sqid::encode(\App\Models\Platform\User::class, $entry?->assigned_user_id)) === $u->sqid)>{{ $u->name }}</option>
        @endforeach
    </x-select-field>

    {{-- Gegenstand des Auftrags (Feature 009; Vollaudit 2026-07, M5). --}}
    <x-select-field name="asset_id" :label="__('Objekt/Asset')">
        <option value="">—</option>
        @foreach (\App\Models\Asset\Asset::query()->orderBy('name')->limit(500)->get(['id', 'name']) as $formAsset)
            <option value="{{ $formAsset->sqid }}" @selected((string) old('asset_id', \App\Support\Sqid::encode(\App\Models\Asset\Asset::class, $entry?->asset_id)) === $formAsset->sqid)>{{ $formAsset->name }}</option>
        @endforeach
    </x-select-field>
</x-form-group>

{{-- Termin / Zeitfenster / Servicedauer --}}
<template x-if="requiresSchedule">
    <x-form-group :legend="__('Termin & Servicezeit')" icon="event" tone="info" cols="3">
        <x-input-field name="scheduled_for" type="date" :label="__('Datum')" :value="old('scheduled_for', optional($entry?->scheduled_for)->format('Y-m-d'))" />

        <x-input-field name="time_window_start" type="time" :label="__('Zeitfenster ab')" :value="old('time_window_start', $entry?->time_window_start)" />

        <x-input-field name="time_window_end" type="time" :label="__('Zeitfenster bis')" :value="old('time_window_end', $entry?->time_window_end)" />

        <div class="fieldset md:col-span-3">
            <label class="fieldset-label" for="service_minutes">{{ __('Servicedauer (Minuten)') }}</label>
            <input
                type="number"
                min="0"
                step="5"
                id="service_minutes"
                name="service_minutes"
                :placeholder="flags.default_service_minutes || ''"
                value="{{ old('service_minutes', $entry?->service_minutes) }}"
                class="input input-bordered w-full @error('service_minutes') input-error @enderror"
            >
            @error('service_minutes')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
    </x-form-group>
</template>

{{-- Adresse --}}
<template x-if="requiresAddress">
    <x-form-group :legend="__('Adresse')" icon="location_on" tone="warning" cols="2">
        <x-input-field name="address_line" :label="__('Straße & Nummer')" maxlength="200" span="2" :value="old('address_line', $entry?->address_line)" />

        <x-input-field name="address_zip" :label="__('PLZ')" maxlength="16" :value="old('address_zip', $entry?->address_zip)" />

        <x-input-field name="address_city" :label="__('Stadt')" maxlength="120" :value="old('address_city', $entry?->address_city)" />

        <x-input-field name="address_country" :label="__('Land (ISO-2)')" maxlength="2" class="uppercase" :value="old('address_country', $entry?->address_country)" />

        <x-input-field name="address_lat" type="number" :label="__('Lat')" step="0.0000001" :value="old('address_lat', $entry?->address_lat)" />

        <x-input-field name="address_lng" type="number" :label="__('Lng')" step="0.0000001" :value="old('address_lng', $entry?->address_lng)" />
    </x-form-group>
</template>

{{-- Tour-Zuordnung --}}
<template x-if="allowTour">
    <x-form-group :legend="__('Tour')" icon="route" tone="accent" cols="2">
        <x-select-field name="tour_id" :label="__('Tour')">
            <option value="">—</option>
            @foreach ($tourOptions as $t)
                <option value="{{ $t->sqid }}" @selected((string) old('tour_id', \App\Support\Sqid::encode(\App\Models\Diary\Tour::class, $entry?->tour_id)) === $t->sqid)>
                    {{ optional($t->tour_date)->format('Y-m-d') }} · {{ $t->name ?? '#'.$t->id }}
                </option>
            @endforeach
        </x-select-field>

        <x-input-field name="tour_position" type="number" :label="__('Position')" min="0" :value="old('tour_position', $entry?->tour_position)" />
    </x-form-group>
</template>

<x-form-group :legend="__('Tags')" icon="flag" tone="success">
    <x-tag-picker :tags="$allTags ?? collect()" :selected="$selectedTagIds ?? []" :recent="$recentTagIds ?? []"
                  suggest-from='[name="content"]' customer-from='[name="customer_id"]' />
</x-form-group>

</div>

<x-custom-fields-group :model="\App\Models\Diary\DiaryEntry::class" :subject="$entry ?? null" />
