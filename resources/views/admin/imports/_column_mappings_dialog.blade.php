{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _column_mappings_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Gespeicherte Spaltenzuordnungen je Importart (MVP-1020).
--}}
<x-modal
    :title="__('import.columns.saved_title')"
    :eyebrow="__('Importe')"
    icon="view_column" tone="primary" size="lg">
    <p class="mb-3 text-sm text-muted">{{ __('import.columns.saved_hint') }}</p>
    @forelse ($mappings as $entity => $rows)
        <h3 class="mb-1 mt-3 text-sm font-semibold">{{ \App\Enums\Import\ImportEntity::from($entity)->label() }}</h3>
        <ul class="divide-y divide-base-300 text-sm">
            @foreach ($rows as $mapping)
                <li class="flex flex-wrap items-center gap-2 py-1.5">
                    <x-status-badge class="font-mono">{{ $mapping->source_header }}</x-status-badge>
                    <x-icon name="arrow_forward" class="text-muted" />
                    <x-status-badge tone="info" class="font-mono">{{ $mapping->target_column }}</x-status-badge>
                    <x-action-form class="ml-auto" :action="route('admin.imports.column-mappings.destroy', $mapping)" method="DELETE"
                                   :confirm="__('import.columns.confirm_delete', ['header' => $mapping->source_header])"
                                   confirm-icon="delete" confirm-tone="error" :confirm-label="__('import.columns.delete')">
                        <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('import.columns.delete')" />
                    </x-action-form>
                </li>
            @endforeach
        </ul>
    @empty
        <x-empty-state icon="view_column" :title="__('import.columns.saved_empty')" compact />
    @endforelse
</x-modal>
