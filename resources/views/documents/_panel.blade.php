{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Dokumente-Panel (MVP-031) für Detailseiten.
  Erwartet: $documentable (Model), $documentableKind ('customer'|'project'|'diary'|'asset')
  Beim Kunden zeigt das Panel die volle Kette (MVP-818) und je Zeile deren Herkunft.
--}}
@php
    $canViewAnyDocuments = \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\Document::class)
        && app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.documents');
@endphp

@if ($canViewAnyDocuments)
@php
    /** @var \App\Models\User $panelViewer */
    $panelViewer = \Illuminate\Support\Facades\Auth::user();
    // Vertrauliche Dokumente Dritter ausblenden — wie in der Liste (Vollaudit
    // 2026-07 N10); das Panel hatte den Filter bis MVP-817 nicht.
    // Beim Kunden die volle Kette (MVP-818): auch die Dokumente an seinen
    // Projekten, Aufträgen und Anlagen — jede andere Akte bleibt bei ihrer
    // direkten Kante.
    $panelQuery = \App\Models\Document::query()
        ->visibleTo($panelViewer)
        ->ofCarrier($documentable);
    $panelTotal = (clone $panelQuery)->count();
    $panelChained = $documentable instanceof \App\Models\Customer;
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $panelDocuments */
    $panelDocuments = $panelQuery
        ->with(['currentVersion'])
        ->when($panelChained, fn ($q) => $q->with(app(\App\Services\Content\ContentSubjectResolver::class)->eagerLoad(\App\Models\Document::class)))
        ->latest('updated_at')
        ->limit(\App\Models\Document::PANEL_LIMIT)
        ->get();
    $canCreateDocument = \Illuminate\Support\Facades\Gate::allows('create', \App\Models\Document::class);
@endphp

<x-card as="section" id="documents" :title="__('document.title.index')" icon="folder_open" :count="$panelTotal">
    @if ($canCreateDocument)
        <x-slot:actions>
            <x-icon-btn icon="note_add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('documents.create', ['documentable_kind' => $documentableKind, 'documentable_id' => \App\Support\Sqid::encode(get_class($documentable), (int) $documentable->getKey())])"
                        show-label>{{ __('document.action.create') }}</x-icon-btn>
        </x-slot:actions>
    @endif

    @if ($panelDocuments->isEmpty())
        <x-empty-state compact icon="folder_open"
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
                            <x-icon :name="$panelDocument->document_type->icon()" class="text-muted" />
                            @can('view', $panelDocument)
                                <a class="link link-hover" href="{{ route('documents.show', $panelDocument) }}">{{ $panelDocument->title }}</a>
                            @else
                                {{ $panelDocument->title }}
                            @endcan
                            <x-status-badge :tone="$panelEffective->tone()" size="sm">{{ $panelEffective->label() }}</x-status-badge>
                        </span>
                        @if ($panelChained && $panelDocument->documentable_type !== \App\Models\Customer::class)
                            <x-subject-link :for="$panelDocument" class="block text-xs" />
                        @endif
                        <span class="block text-xs text-muted">
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
        @if ($panelTotal > $panelDocuments->count())
            <p class="pt-3 text-xs text-muted">
                {{ __('document.panel.truncated', ['shown' => $panelDocuments->count(), 'total' => $panelTotal]) }}
            </p>
        @endif
    @endif
</x-card>
@endif
