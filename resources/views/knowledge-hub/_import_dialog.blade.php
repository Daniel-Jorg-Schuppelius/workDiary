{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _import_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Übernahme aus Obsidian oder einer Notizbuch-Quelle (MVP-815, MVP-1042).
  Variablen: $source (`obsidian` oder Schlüssel der Quelle), $notebookSource
  (?NotebookSource), $targets (Zieltypen), $connections (aktive
  Ordner-Anbindungen), $notebooks, $notebookError.
--}}
@php
    $isNotebook = $notebookSource !== null;
    $ready = $isNotebook ? $notebooks !== [] : $connections->isNotEmpty();
@endphp
<x-modal
    :title="__('collections.import.' . $source . '.title')"
    :eyebrow="__('collections.hub.title')"
    :icon="$isNotebook ? $notebookSource->icon() : 'folder_zip'"
    tone="primary"
    :action="$ready ? ($isNotebook ? route('knowledge-imports.notebook', $source) : route('knowledge-imports.obsidian')) : null"
    :submit-label="$ready ? __('collections.import.action.start') : null">

    <p class="text-sm text-muted">{{ __('collections.import.' . $source . '.intro') }}</p>

    @if (! $ready)
        <x-empty-state compact icon="link_off"
                       :title="__('collections.import.' . $source . '.title')"
                       :message="__('collections.import.' . $source . '.' . ($notebookError ? 'error' : 'none'))" />
    @else
        <x-form-group :legend="__('collections.import.source')" icon="input" tone="primary">
            @if ($isNotebook)
                <x-select-field name="notebook" :label="__('collections.import.' . $source . '.notebook')" required>
                    @foreach ($notebooks as $notebook)
                        <option value="{{ $notebook['id'] }}">{{ $notebook['name'] }}</option>
                    @endforeach
                </x-select-field>
            @else
                <x-select-field name="connection" :label="__('collections.import.obsidian.connection')" required>
                    @foreach ($connections as $connection)
                        <option value="{{ \App\Support\Sqid::encode($connection::class, (int) $connection->id) }}">{{ $connection->name }}{{ $connection->root_folder_path ? ' · ' . $connection->root_folder_path : '' }}</option>
                    @endforeach
                </x-select-field>
                <x-input-field name="vault_path" :label="__('collections.import.obsidian.vault_path')" maxlength="500"
                               :hint="__('collections.import.obsidian.vault_path_hint')" placeholder="Obsidian/Arbeit" />
            @endif
        </x-form-group>

        <x-form-group :legend="__('collections.import.target')" icon="output" tone="ghost">
            <x-select-field name="target" :label="__('collections.import.target')" required>
                @foreach ($targets as $target)
                    <option value="{{ $target }}">{{ __('collections.type.' . $target) }}</option>
                @endforeach
            </x-select-field>
            <x-input-field name="title" :label="__('collections.import.root_title')" maxlength="180" :hint="__('collections.import.root_title_hint')" />
        </x-form-group>

        <p class="text-xs text-muted">{{ __('collections.import.rules', ['max' => \App\Services\Collections\Import\KnowledgeImportService::MAX_DOCUMENTS]) }}</p>
    @endif
</x-modal>
