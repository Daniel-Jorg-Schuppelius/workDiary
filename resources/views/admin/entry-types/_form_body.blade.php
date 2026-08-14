{{--
  Created on   : Mon May 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_body.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php $skipStatusControls = $skipStatusControls ?? false; @endphp

{{-- Shared body for EntryType create/edit (standalone + dialog) --}}
@php
    /** @var \App\Models\EntryType $entryType */
    $isEdit = $entryType?->exists ?? false;
    $statusOptions = $statusOptions ?? [];
    $priorityOptions = $priorityOptions ?? \App\Enums\Diary\Priority::cases();
@endphp

<x-form-group :legend="__('Identifikation')" icon="badge" tone="primary" cols="2">
    <x-filter-field show-label :label="__('Bezeichnung')" for="entrytype-label">
        <input type="text" id="entrytype-label" name="label" required maxlength="120"
               value="{{ old('label', $entryType->label) }}"
               class="input input-bordered input-sm w-full">
    </x-filter-field>

    <x-filter-field show-label :label="__('Slug')" for="entrytype-slug">
        <input type="text" id="entrytype-slug" name="slug" required maxlength="64"
               pattern="[a-z0-9_]+"
               value="{{ old('slug', $entryType->slug) }}"
               class="input input-bordered input-sm w-full font-mono"
               @if ($isEdit) readonly @endif>
    </x-filter-field>

    <x-filter-field show-label :label="__('Icon (Material Symbol)')" for="entrytype-icon">
        <input type="text" id="entrytype-icon" name="icon" required maxlength="64"
               value="{{ old('icon', $entryType->icon ?: 'task_alt') }}"
               class="input input-bordered input-sm w-full font-mono">
    </x-filter-field>

    <x-filter-field show-label :label="__('Farbe')" for="entrytype-color">
        @php
            $colorOptions = [
                'primary'   => __('Primär (Hauptaktion)'),
                'secondary' => __('Sekundär (Ergänzend)'),
                'accent'    => __('Akzent (Hervorhebung)'),
                'info'      => __('Info (Hinweis, Blau)'),
                'success'   => __('Erfolg (Grün)'),
                'warning'   => __('Warnung (Gelb/Orange)'),
                'error'     => __('Fehler (Rot)'),
                'ghost'     => __('Neutral (Dezent)'),
            ];
            $currentColor = old('color', $entryType->color ?: 'primary');
        @endphp
        <div class="flex items-center gap-2">
            <span class="inline-block h-3 w-3 rounded-full border border-base-300" aria-hidden="true"
                  data-color-preview style="background-color: var(--color-{{ $currentColor }});"></span>
            <select id="entrytype-color" name="color" class="select select-bordered select-sm w-full"
                    data-color-preview>
                @foreach ($colorOptions as $tone => $label)
                    <option value="{{ $tone }}" @selected($currentColor === $tone)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <p class="mt-1 text-[0.7rem] text-base-content/60">
            {{ __('Bestimmt die Akzentfarbe für Icon, Badge und Hervorhebungen in Listen.') }}
        </p>
    </x-filter-field>

    <div class="md:col-span-2">
        <x-filter-field show-label :label="__('Beschreibung')" for="entrytype-description">
            <input type="text" id="entrytype-description" name="description" maxlength="255"
                   value="{{ old('description', $entryType->description) }}"
                   class="input input-bordered input-sm w-full">
        </x-filter-field>
    </div>

    <x-filter-field show-label :label="__('Sortierung')" for="entrytype-sort">
        <input type="number" id="entrytype-sort" name="sort" min="0" max="9999"
               value="{{ old('sort', $entryType->sort ?? 100) }}"
               class="input input-bordered input-sm w-full">
    </x-filter-field>

    @unless ($skipStatusControls)
    <x-filter-field show-label :label="__('Aktiv')" for="entrytype-is-active">
        <label class="label cursor-pointer justify-start gap-3">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" id="entrytype-is-active" name="is_active" value="1"
                   class="toggle toggle-success"
                   @checked(old('is_active', $isEdit ? $entryType->is_active : true))>
            <span class="label-text">{{ __('Typ verfügbar') }}</span>
        </label>
    </x-filter-field>
    @endunless
</x-form-group>

<x-form-group :legend="__('Pflicht-/Optional-Felder')" icon="rule" tone="info" cols="2"
              :description="__('Steuert, welche Felder bei diesem Eintragstyp angezeigt und erzwungen werden.')">
    @foreach ([
        'requires_customer' => __('Kunde erforderlich'),
        'requires_schedule' => __('Termin/Zeitfenster erforderlich'),
        'requires_address'  => __('Einsatzadresse erforderlich'),
        'requires_tour'     => __('Tour-Zuordnung erforderlich'),
        'allow_priority'    => __('Priorität auswählbar'),
        'allow_tour'        => __('Tour-Zuordnung optional erlauben'),
    ] as $field => $label)
        <label class="label cursor-pointer justify-start gap-3">
            <input type="hidden" name="{{ $field }}" value="0">
            <input type="checkbox" name="{{ $field }}" value="1" class="checkbox checkbox-sm checkbox-primary"
                   @checked(old($field, $entryType->{$field} ?? false))>
            <span class="label-text">{{ $label }}</span>
        </label>
    @endforeach
</x-form-group>

<x-form-group :legend="__('Standardwerte')" icon="tune" tone="success" cols="3">
    <x-filter-field show-label :label="__('Status (Default)')" for="entrytype-default-status">
        <select id="entrytype-default-status" name="default_status" class="select select-bordered select-sm w-full">
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}" @selected((int) old('default_status', $entryType->default_status ?? \App\Enums\Diary\Status::Open->value) === (int) $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-filter-field>

    <x-filter-field show-label :label="__('Priorität (Default)')" for="entrytype-default-priority">
        <select id="entrytype-default-priority" name="default_priority" class="select select-bordered select-sm w-full">
            <option value="">— {{ __('keine Vorgabe') }} —</option>
            @foreach ($priorityOptions as $prio)
                @php($prioValue = $prio instanceof \App\Enums\Diary\Priority ? $prio->value : $prio)
                @php($prioLabel = $prio instanceof \App\Enums\Diary\Priority ? $prio->label() : __(ucfirst((string) $prio)))
                <option value="{{ $prioValue }}" @selected(old('default_priority', $entryType->default_priority?->value) === $prioValue)>{{ $prioLabel }}</option>
            @endforeach
        </select>
    </x-filter-field>

    <x-filter-field show-label :label="__('Servicedauer (Min., Default)')" for="entrytype-default-minutes">
        <input type="number" id="entrytype-default-minutes" name="default_service_minutes" min="0" max="10080"
               value="{{ old('default_service_minutes', $entryType->default_service_minutes) }}"
               class="input input-bordered input-sm w-full">
    </x-filter-field>
</x-form-group>
