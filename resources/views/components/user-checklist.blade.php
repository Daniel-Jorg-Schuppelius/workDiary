@props([
    'name',                 // Feldname, submitted als name[]
    'users' => [],          // iterierbar, Objekte mit valueKey/labelKey
    'selected' => [],       // Array bereits gewählter Werte (i. d. R. Sqids)
    'valueKey' => 'sqid',
    'labelKey' => 'name',
    'height' => 'max-h-56',
    'placeholder' => null,
    'emptyText' => null,
])

{{--
    <x-user-checklist> — kompakte, scrollbare Auswahl-Liste mit Häkchen und
    Sofort-Suche. Ersetzt große Checkbox-Raster, wenn viele Mitarbeiter zur
    Auswahl stehen. Submit als {{ $name }}[] (Sqid). Auswahl bleibt beim
    Tippen/Filtern erhalten (echte Checkboxen, nur sichtbar gefiltert).
--}}

@php
    $checklistSelected = collect(old($name, $selected))->map(fn ($v) => (string) $v)->all();
    $checklistItems = collect($users)->map(fn ($u) => [
        'value' => (string) (is_array($u) ? ($u[$valueKey] ?? '') : ($u->{$valueKey} ?? '')),
        'label' => (string) (is_array($u) ? ($u[$labelKey] ?? '') : ($u->{$labelKey} ?? '')),
    ])->filter(fn ($i) => $i['value'] !== '')->values();
@endphp

<div {{ $attributes->merge(['class' => 'space-y-1']) }}
     x-data="userChecklist({{ count($checklistSelected) }})">
    @if ($checklistItems->isEmpty())
        <p class="text-xs text-base-content/60">{{ $emptyText ?? __('Keine Einträge vorhanden.') }}</p>
    @else
        <label class="input input-bordered input-sm flex w-full items-center gap-2">
            <span class="material-symbols-outlined text-[1.05rem] opacity-50" aria-hidden="true">search</span>
            <input type="text" x-model="q" autocomplete="off" class="grow"
                   placeholder="{{ $placeholder ?? __('Suchen…') }}">
        </label>

        <div class="{{ $height }} divide-y divide-base-200 overflow-y-auto rounded-box border border-base-300">
            @foreach ($checklistItems as $item)
                <label class="flex cursor-pointer items-center gap-2 px-2 py-1.5 hover:bg-base-200"
                       x-show="visible({{ \Illuminate\Support\Js::from(\Illuminate\Support\Str::lower($item['label'])) }})">
                    <input type="checkbox" name="{{ $name }}[]" value="{{ $item['value'] }}"
                           class="checkbox checkbox-sm"
                           @checked(in_array($item['value'], $checklistSelected, true))
                           @change="adjust($event)">
                    <span class="text-sm">{{ $item['label'] }}</span>
                </label>
            @endforeach
        </div>

        <p class="text-xs text-base-content/50">
            <span x-text="count"></span> {{ __('ausgewählt') }}
        </p>
    @endif
</div>
