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
    $defaultUserId = old('user_id', $entry?->user_id ?? ($prefillUserId ?: auth()->id()));

    // Phase 6: EntryType-gesteuerte Felder
    $entryTypes = $entryTypes ?? collect();
    $entryTypeFlags = $entryTypeFlags ?? [];
    $customerOptions = $customerOptions ?? collect();
    $tourOptions = $tourOptions ?? collect();
    $rawDefaultEntryTypeId = old('entry_type_id', $entry?->entry_type_id ?? ($prefillEntryTypeId ?? 0));
    $defaultEntryTypeSqid = '0';
    if (is_numeric($rawDefaultEntryTypeId) && (int) $rawDefaultEntryTypeId > 0) {
        $defaultEntryTypeSqid = \App\Support\Sqid::encode(\App\Models\EntryType::class, (int) $rawDefaultEntryTypeId);
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
                <option value="{{ $u->sqid }}" @selected((string) old('user_id', \App\Support\Sqid::encode(\App\Models\User::class, $defaultUserId)) === $u->sqid)>{{ $u->name }}</option>
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
    <x-textarea-field name="content" :label="__('Inhalt')" required rows="8" placeholder="{{ __('Beschreibe den Vorgang...') }}" :value="old('content', $entry?->content ?? $prefillContent)" />

    <x-textarea-field name="response" :label="__('Rückmeldung')" rows="4" placeholder="{{ __('Antwort oder Notiz (optional) ...') }}" :value="old('response', $entry?->response)" />
</x-form-group>

<input type="hidden" name="status" value="{{ $entry?->status?->value ?? \App\Enums\Diary\Status::Planned->value }}">
@if (! $isEdit && $prefillOpenIssueSqid !== null)
    {{-- Folgeauftrag (Feature 139): Rückverknüpfung + Projekt aus dem Subjekt des Punkts. --}}
    <input type="hidden" name="open_issue_id" value="{{ old('open_issue_id', $prefillOpenIssueSqid) }}">
    @if ($prefillProjectId !== null)
        <input type="hidden" name="project_id" value="{{ old('project_id', \App\Support\Sqid::encode(\App\Models\Project::class, $prefillProjectId)) }}">
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
            value="{{ old('due_date', $entry?->due_date?->format('Y-m-d')) }}"
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
        <p class="text-sm text-muted">{{ __('Kein Datum erfasst — erscheint im Backlog und kann später terminiert werden.') }}</p>
    </div>
</x-form-group>

{{-- Kunde / zugewiesener Benutzer: immer verfügbar (Server erlaubt Kunde
     auch ohne fordernden Typ); Pflicht-Markierung nur typabhängig. Früher
     stand der Block hinter x-if="requiresCustomer" — damit war ein Kunde
     bei nicht-fordernden Typen nie zuweisbar und Bestandswerte unsichtbar. --}}
<x-form-group :legend="__('Kunde & Zuweisung')" icon="badge" tone="secondary" cols="2">
    <x-select-field name="customer_id" :label="__('Kunde')" x-bind:required="requiresCustomer">
        <option value="">—</option>
        @foreach ($customerOptions as $c)
            <option value="{{ $c->sqid }}" @selected((string) old('customer_id', \App\Support\Sqid::encode(\App\Models\Customer::class, $entry?->customer_id ?? $prefillCustomerId)) === $c->sqid)>
                {{ $c->name }}@if ($c->company) — {{ $c->company }}@endif
            </option>
        @endforeach
    </x-select-field>

    <x-select-field name="assigned_user_id" :label="__('Zuständig')">
        <option value="">—</option>
        @foreach ($assignableUsers as $u)
            <option value="{{ $u->sqid }}" @selected((string) old('assigned_user_id', \App\Support\Sqid::encode(\App\Models\User::class, $entry?->assigned_user_id)) === $u->sqid)>{{ $u->name }}</option>
        @endforeach
    </x-select-field>

    {{-- Gegenstand des Auftrags (Feature 009; Vollaudit 2026-07, M5). --}}
    <x-select-field name="asset_id" :label="__('Objekt/Asset')">
        <option value="">—</option>
        @foreach (\App\Models\Asset::query()->orderBy('name')->limit(500)->get(['id', 'name']) as $formAsset)
            <option value="{{ $formAsset->sqid }}" @selected((string) old('asset_id', \App\Support\Sqid::encode(\App\Models\Asset::class, $entry?->asset_id)) === $formAsset->sqid)>{{ $formAsset->name }}</option>
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
                <option value="{{ $t->sqid }}" @selected((string) old('tour_id', \App\Support\Sqid::encode(\App\Models\Tour::class, $entry?->tour_id)) === $t->sqid)>
                    {{ optional($t->tour_date)->format('Y-m-d') }} · {{ $t->name ?? '#'.$t->id }}
                </option>
            @endforeach
        </x-select-field>

        <x-input-field name="tour_position" type="number" :label="__('Position')" min="0" :value="old('tour_position', $entry?->tour_position)" />
    </x-form-group>
</template>

<x-form-group :legend="__('Tags')" icon="flag" tone="success">
    @php
        $tagPickerAll = ($allTags ?? collect())->map(fn ($t) => [
            'id' => $t->sqid,
            'name' => $t->name,
            'color' => $t->color,
        ])->values()->all();
        $tagPickerSelected = collect(old('tag_ids', $selectedTagIds ?? []))
            ->map(fn ($v) => (string) $v)->filter()->unique()->values()->all();
        $tagPickerRecent = collect($recentTagIds ?? [])
            ->map(fn ($v) => (string) $v)->values()->all();
        // Bei Validierungsfehlern eingegebene neue Tags wiederherstellen.
        $tagPickerNew = collect(preg_split('/[,;\n]+/', (string) old('new_tags', '')) ?: [])
            ->map(fn ($v) => trim((string) $v))->filter()->values()->all();
        // KI-Tagvorschläge (Feature 143, MVP-711): nur mit nutzbarer Capability.
        $tagSuggestUrl = app(\App\Services\Ai\Suggestions\SuggestionViewData::class)->capabilityUsable(\App\Services\Ai\Suggestions\ClassificationSuggestionService::CAPABILITY)
            ? route('ai.suggest.tags')
            : null;
        $tagPickerConfig = ['all' => $tagPickerAll, 'selectedIds' => $tagPickerSelected, 'recentIds' => $tagPickerRecent, 'initialNew' => $tagPickerNew, 'quickLimit' => 8, 'allowCreate' => true, 'suggestUrl' => $tagSuggestUrl, 'textSelector' => '[name="content"]', 'customerSelector' => '[name="customer_id"]'];
    @endphp
    <div class="fieldset"
         x-data="tagPicker"
         data-config="{{ json_encode($tagPickerConfig) }}"
         @click.outside="close()">

        {{-- Versteckte Felder für den Submit --}}
        <template x-for="id in existingIds" :key="id">
            <input type="hidden" name="tag_ids[]" :value="id">
        </template>
        <input type="hidden" name="new_tags" :value="newNamesText">

        {{-- Ausgewählte Tags als entfernbare Chips --}}
        <div class="flex flex-wrap gap-2" x-show="hasSelected" x-cloak>
            <template x-for="tag in selected" :key="tag.key">
                <span class="badge gap-1"
                      :class="chipClass(tag)"
                      :style="chipStyle(tag)">
                    <span x-text="tag.name"></span>
                    <button type="button" class="opacity-70 hover:opacity-100"
                            aria-label="{{ __('Tag entfernen') }}"
                            @click="remove(tag)">&times;</button>
                </span>
            </template>
        </div>

        {{-- Schnellauswahl: zuletzt verwendete Tags --}}
        <div class="flex flex-wrap gap-2 mt-2" x-show="hasQuickPicks" x-cloak>
            <template x-for="tag in quickPicks" :key="tag.id">
                <button type="button"
                        class="badge badge-outline transition-colors hover:bg-primary hover:border-primary hover:text-primary-content"
                        @click="addExisting(tag)">
                    <span x-show="tag.color" class="inline-block w-2 h-2 rounded-full mr-1"
                          :style="dotStyle(tag)"></span>
                    <span x-text="tag.name"></span>
                </button>
            </template>
        </div>

        @if ($tagSuggestUrl !== null)
            <x-tag-picker-ai />
        @endif

        {{-- Such-/Eingabefeld mit Dropdown --}}
        <div class="relative mt-2">
            <input type="text"
                   x-model="query"
                   @focus="openMenu()"
                   @input="onInput()"
                   @keydown.enter.prevent="enterPressed()"
                   @keydown.arrow-down.prevent="move(1)"
                   @keydown.arrow-up.prevent="move(-1)"
                   @keydown.escape="close()"
                   autocomplete="off"
                   class="input input-bordered input-sm w-full"
                   placeholder="{{ __('Tag suchen oder neuen Tag eingeben…') }}">

            <ul x-show="showMenu" x-cloak x-transition.opacity
                class="menu menu-sm absolute z-30 mt-1 w-full max-h-56 flex-nowrap overflow-y-auto rounded-box border border-base-300 bg-base-100 shadow-lg">
                <template x-for="(tag, idx) in filtered" :key="tag.id">
                    <li>
                        <button type="button"
                                :class="optionClass(idx)"
                                @mouseenter="setHighlight(idx)"
                                @click="addExisting(tag)">
                            <span x-show="tag.color" class="inline-block w-2 h-2 rounded-full"
                                  :style="dotStyle(tag)"></span>
                            <span x-text="tag.name"></span>
                        </button>
                    </li>
                </template>
                <li x-show="canCreate">
                    <button type="button" class="text-success" @click="createNew()">
                        <span>{{ __('Neu anlegen:') }} „<span x-text="queryTrimmed"></span>"</span>
                    </button>
                </li>
            </ul>
        </div>

        <p class="text-xs text-muted mt-1">
            {{ __('Auf einen Tag klicken oder tippen, um zu suchen bzw. neue Tags anzulegen.') }}
        </p>
    </div>
</x-form-group>

</div>
