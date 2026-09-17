{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _import_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Übernahme aus Obsidian oder OneNote (MVP-815). Variablen: $source
  ('obsidian'|'onenote'), $targets (Zieltypen), $connections (aktive
  Ordner-Anbindungen), $notebooks (OneNote), $oneNoteError.
--}}
@php
    $isOneNote = $source === 'onenote';
    $ready = $isOneNote ? $notebooks !== [] : $connections->isNotEmpty();
@endphp
<x-modal
    :title="$isOneNote ? __('collections.import.onenote.title') : __('collections.import.obsidian.title')"
    :eyebrow="__('collections.hub.title')"
    :icon="$isOneNote ? 'book' : 'folder_zip'"
    tone="primary"
    :action="$ready ? route($isOneNote ? 'knowledge-imports.onenote' : 'knowledge-imports.obsidian') : null"
    :submit-label="$ready ? __('collections.import.action.start') : null">

    <p class="text-sm text-muted">{{ $isOneNote ? __('collections.import.onenote.intro') : __('collections.import.obsidian.intro') }}</p>

    @if (! $ready)
        <x-empty-state compact icon="link_off"
                       :title="$isOneNote ? __('collections.import.onenote.title') : __('collections.import.obsidian.title')"
                       :message="$isOneNote ? ($oneNoteError ? __('collections.import.onenote.error') : __('collections.import.onenote.none')) : __('collections.import.obsidian.none')" />
    @else
        <x-form-group :legend="__('collections.import.source')" icon="input" tone="primary">
            @if ($isOneNote)
                <x-select-field name="notebook" :label="__('collections.import.onenote.notebook')" required>
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
