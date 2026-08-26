{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Editor für eigene Arbeitsbereiche (Feature 082 Phase 2, MVP-731).

    Links der Katalog dessen, was die Person laut NavGate sehen darf (der
    Server prüft dieselbe Liste beim Speichern erneut), rechts die gewählte
    Reihenfolge. Sortiert wird per Pointer-Events-Drag-and-drop ODER über die
    Schaltflächen „Nach oben"/„Nach unten" bzw. die Pfeiltasten am Griff —
    ohne Maus geht es genauso. Logik in resources/js/workspace-editor.js
    (CSP-konform, keine Inline-Handler).

    Variablen: $workspace, $isEdit, $catalog, $selected
--}}
@php
    /** @var \App\Models\UserWorkspace $workspace */
    /** @var bool $isEdit */
    /** @var list<array<string, mixed>> $catalog */
    /** @var list<string> $selected */
    $isEdit ??= $workspace->exists;
    $action = $isEdit ? route('me.workspaces.update', $workspace) : route('me.workspaces.store');
    $method = $isEdit ? 'PUT' : 'POST';
    $title = $isEdit ? __('scope.workspace.edit') : __('scope.workspace.create');

    // Beschriftungen der Auswahl (Chips) aus dem Katalog auflösen — ein Punkt,
    // den es nicht mehr gibt, verschwindet damit still aus der Auswahl.
    $labels = [];
    $icons = [];
    foreach ($catalog as $section) {
        $labels[$section['key']] = $section['label'];
        $icons[$section['key']] = $section['icon'];
        foreach ($section['entries'] as $entry) {
            $labels[$entry['key']] = $entry['label'];
            $icons[$entry['key']] = $entry['icon'];
        }
    }
    $chosen = array_values(array_filter(old('items', $selected) ?? [], static fn ($key): bool => isset($labels[$key])));
@endphp

<x-modal
    :title="$title"
    :eyebrow="__('scope.workspace.title')"
    icon="dashboard_customize"
    tone="primary"
    size="wide"
    :action="$action"
    :method="$method"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    <x-form-group :legend="__('scope.workspace.title')" icon="dashboard_customize" tone="primary" cols="3">
        <x-input-field name="name" :label="__('scope.workspace.name')" required maxlength="60" :value="old('name', $workspace->name)" />
        <x-input-field name="icon" :label="__('scope.workspace.icon')" maxlength="40" class="font-mono" placeholder="dashboard_customize" :value="old('icon', $workspace->icon)" />
        <x-input-field name="sort" type="number" :label="__('scope.workspace.sort')" min="0" max="999" :value="old('sort', $workspace->sort ?? 0)" />
    </x-form-group>

    <div data-workspace-editor class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- Katalog: nur, was die Person ohnehin sehen darf --}}
        <div class="rounded-box border border-base-300">
            <div class="flex items-center gap-2 border-b border-base-300 px-3 py-2">
                <span class="text-sm font-semibold">{{ __('scope.workspace.available') }}</span>
                <input type="search" data-workspace-filter
                       class="input input-xs input-bordered ml-auto w-40"
                       placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
            </div>
            <div data-workspace-catalog class="max-h-80 overflow-y-auto p-2">
                @foreach ($catalog as $section)
                    <div data-workspace-section class="mb-2">
                        <button type="button" data-workspace-add
                                data-key="{{ $section['key'] }}"
                                class="flex w-full items-center gap-2 rounded-field px-2 py-1.5 text-left text-xs font-semibold uppercase tracking-wider text-muted hover:bg-base-200">
                            <x-icon :name="$section['icon']" class="text-[1.05rem]" />
                            <span data-workspace-text>{{ $section['label'] }}</span>
                            <x-icon name="add" class="ml-auto text-[1rem] opacity-50" />
                        </button>
                        @foreach ($section['entries'] as $entry)
                            <button type="button" data-workspace-add
                                    data-key="{{ $entry['key'] }}"
                                    class="flex w-full items-center gap-2 rounded-field py-1 pr-2 text-left text-sm hover:bg-base-200 {{ $entry['level'] > 0 ? 'pl-8' : 'pl-4' }}">
                                <x-icon :name="$entry['icon']" class="text-[1.05rem] opacity-70" />
                                <span data-workspace-text>{{ $entry['label'] }}</span>
                                <x-icon name="add" class="ml-auto text-[1rem] opacity-50" />
                            </button>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Auswahl: Reihenfolge ist die Aussage --}}
        <div class="rounded-box border border-base-300">
            <div class="flex items-center gap-2 border-b border-base-300 px-3 py-2">
                <span class="text-sm font-semibold">{{ __('scope.workspace.selected') }}</span>
                <span class="badge badge-ghost badge-sm ml-auto" data-workspace-count>{{ count($chosen) }}</span>
            </div>
            <ol data-workspace-order class="max-h-80 space-y-1 overflow-y-auto p-2">
                @foreach ($chosen as $key)
                    <li data-workspace-chip data-key="{{ $key }}"
                        class="flex items-center gap-2 rounded-field border border-base-300 bg-base-100 px-2 py-1.5">
                        <input type="hidden" name="items[]" value="{{ $key }}" />
                        <span data-workspace-handle tabindex="0" role="button"
                              aria-label="{{ __('scope.workspace.drag_hint') }}"
                              class="cursor-grab select-none text-muted">
                            <x-icon name="drag_indicator" class="text-[1.1rem]" />
                        </span>
                        <x-icon :name="$icons[$key] ?? 'circle'" class="text-[1.05rem] opacity-70" />
                        <span class="truncate text-sm">{{ $labels[$key] }}</span>
                        <span class="ml-auto flex items-center gap-1">
                            <x-icon-btn icon="arrow_upward" size="xs" tone="ghost" type="button"
                                        data-workspace-up :label="__('scope.workspace.move_up')" />
                            <x-icon-btn icon="arrow_downward" size="xs" tone="ghost" type="button"
                                        data-workspace-down :label="__('scope.workspace.move_down')" />
                            <x-icon-btn icon="close" size="xs" tone="error" type="button"
                                        data-workspace-remove :label="__('scope.workspace.remove')" />
                        </span>
                    </li>
                @endforeach
            </ol>
            <p data-workspace-empty class="px-3 pb-3 text-xs text-muted {{ $chosen === [] ? '' : 'hidden' }}">
                {{ __('scope.workspace.error.no_items') }}
            </p>
            <p class="border-t border-base-300 px-3 py-2 text-xs text-muted">{{ __('scope.workspace.drag_hint') }}</p>
        </div>

        {{-- Vorlage einer Auswahl-Zeile (vom Editor geklont; keine Inline-Skripte) --}}
        <template data-workspace-template>
            <li data-workspace-chip data-key=""
                class="flex items-center gap-2 rounded-field border border-base-300 bg-base-100 px-2 py-1.5">
                <input type="hidden" name="items[]" value="" />
                <span data-workspace-handle tabindex="0" role="button"
                      aria-label="{{ __('scope.workspace.drag_hint') }}"
                      class="cursor-grab select-none text-muted">
                    <x-icon name="drag_indicator" class="text-[1.1rem]" />
                </span>
                <x-icon name="circle" data-workspace-icon class="text-[1.05rem] opacity-70" />
                <span data-workspace-label class="truncate text-sm"></span>
                <span class="ml-auto flex items-center gap-1">
                    <x-icon-btn icon="arrow_upward" size="xs" tone="ghost" type="button"
                                data-workspace-up :label="__('scope.workspace.move_up')" />
                    <x-icon-btn icon="arrow_downward" size="xs" tone="ghost" type="button"
                                data-workspace-down :label="__('scope.workspace.move_down')" />
                    <x-icon-btn icon="close" size="xs" tone="error" type="button"
                                data-workspace-remove :label="__('scope.workspace.remove')" />
                </span>
            </li>
        </template>
    </div>

    @error('items')
        <p class="mt-2 text-sm text-error">{{ $message }}</p>
    @enderror
    <p class="mt-2 text-xs text-muted">{{ __('scope.workspace.items_hint') }}</p>
</x-modal>
