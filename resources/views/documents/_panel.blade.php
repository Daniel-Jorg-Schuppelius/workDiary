{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Dokumente-Panel (MVP-031) für Detailseiten.
  Erwartet: $documentable (Model), $documentableKind ('customer'|'project'|'diary'|'asset')
--}}
@php
    $canViewAnyDocuments = \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\Document::class)
        && app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.documents');
@endphp

@if ($canViewAnyDocuments)
@php
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $panelDocuments */
    $panelDocuments = \App\Models\Document::query()
        ->where('documentable_type', get_class($documentable))
        ->where('documentable_id', $documentable->getKey())
        ->with(['currentVersion'])
        ->latest('updated_at')
        ->get();
    $canCreateDocument = \Illuminate\Support\Facades\Gate::allows('create', \App\Models\Document::class);
@endphp

<x-card as="section" id="documents">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold text-base-content">
            <x-icon name="folder_open" class="text-base-content/60" /> {{ __('document.title.index') }}
            <span class="font-normal text-base-content/50">({{ $panelDocuments->count() }})</span>
        </h2>
        @if ($canCreateDocument)
            <x-icon-btn icon="note_add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('documents.create', ['documentable_kind' => $documentableKind, 'documentable_id' => \App\Support\Sqid::encode(get_class($documentable), (int) $documentable->getKey())])"
                        show-label>{{ __('document.action.create') }}</x-icon-btn>
        @endif
    </div>

    @if ($panelDocuments->isEmpty())
        <x-empty-state compact icon='<span class="material-symbols-outlined">folder_open</span>'
                       :title="__('document.title.index')"
                       :message="__('document.empty')" />
    @else
        <ul class="divide-y divide-base-300">
            @foreach ($panelDocuments as $panelDocument)
                @php
                    $panelEffective = $panelDocument->effectiveStatus();
                @endphp
                <li id="document-{{ $panelDocument->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                    <div class="min-w-0">
                        <span class="flex items-center gap-2 font-medium">
                            <x-icon :name="$panelDocument->document_type->icon()" class="text-base-content/60" />
                            {{ $panelDocument->title }}
                            <x-status-badge :tone="$panelEffective->tone()" size="sm">{{ $panelEffective->label() }}</x-status-badge>
                        </span>
                        <span class="block text-xs text-base-content/60">
                            {{ $panelDocument->document_type->label() }}
                            · v{{ $panelDocument->currentVersion?->version_no ?? '—' }}
                            @if ($panelDocument->valid_until)
                                · {{ __('document.field.valid_until') }}: {{ $panelDocument->valid_until->fdate() }}
                            @endif
                        </span>
                    </div>
                    <div class="flex gap-1">
                        @if ($panelDocument->currentVersion !== null)
                            <x-icon-btn icon="download" tone="outline" size="xs"
                                        :href="route('documents.download', $panelDocument)"
                                        :label="__('document.action.download')" />
                        @endif
                        <x-icon-btn icon="history" tone="outline" size="xs"
                                    data-entry-modal-trigger
                                    :href="route('documents.versions', $panelDocument)"
                                    :label="__('document.title.versions')" />
                        @can('update', $panelDocument)
                            <x-icon-btn icon="edit" tone="outline" size="xs"
                                        data-entry-modal-trigger
                                        :href="route('documents.edit', $panelDocument)"
                                        :label="__('document.action.edit')" />
                        @endcan
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
@endif
