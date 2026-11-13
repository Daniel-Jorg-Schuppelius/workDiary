{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _linked_card.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Karte „Wissensseiten“ an Auftrag, Ticket und Prozedurvorlage (Feature 191,
  MVP-1667): verknüpfte Seiten, die die Person lesen darf, und — mit
  Schreibrecht — eine Seite verknüpfen. Kunde, Projekt und Asset zeigen
  verknüpfte Seiten im Cockpit. Erwartet: $subject (Model).
--}}
@php
    $linkedViewer = auth()->user();
    $linkedEnabled = $linkedViewer instanceof \App\Models\Platform\User
        && app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.knowledge')
        && \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\Knowledge\KnowledgePage::class);
    $linkedService = app(\App\Services\Knowledge\Pages\KnowledgePageLinks::class);
    $linkedType = $linkedEnabled ? $linkedService->keyFor($subject) : null;
    $linkedPages = $linkedType !== null ? $linkedService->pagesFor($subject, $linkedViewer) : collect();
    $linkedCanLink = $linkedType !== null && \Illuminate\Support\Facades\Gate::allows('create', \App\Models\Knowledge\KnowledgePage::class);
    $linkedOptions = $linkedCanLink ? $linkedService->pageOptions($subject, $linkedViewer) : [];
@endphp
@if ($linkedType !== null && ($linkedPages->isNotEmpty() || $linkedOptions !== []))
    <x-card as="section" id="knowledge-pages-linked" :title="__('knowledge_pages.cockpit.title')" icon="auto_stories" :count="$linkedPages->count()">
        @if ($linkedPages->isEmpty())
            <x-empty-state compact icon="auto_stories" :title="__('knowledge_pages.links.none_for_subject')" />
        @else
            <ul class="mb-3 space-y-1 text-sm" role="list">
                @foreach ($linkedPages as $linkedPage)
                    <li class="flex flex-wrap items-center gap-2">
                        <x-icon :name="$linkedPage->kind->icon()" class="text-muted" />
                        <a class="link link-hover" href="{{ route('knowledge.pages.show', $linkedPage) }}">{{ $linkedPage->title }}</a>
                        <x-status-badge :tone="$linkedPage->status->tone()" size="xs">{{ $linkedPage->status->label() }}</x-status-badge>
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($linkedOptions !== [])
            <form method="POST" action="{{ route('knowledge.pages.links.store') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <input type="hidden" name="target" value="{{ $linkedType }}:{{ \App\Support\Sqid::encode($subject::class, (int) $subject->getKey()) }}">
                <label for="kp-link-page-{{ $linkedType }}" class="sr-only">{{ __('knowledge_pages.links.choose_page') }}</label>
                <select id="kp-link-page-{{ $linkedType }}" name="page" class="select select-sm select-bordered min-w-0 flex-1" required>
                    <option value="">{{ __('knowledge_pages.links.choose_page') }}</option>
                    @foreach ($linkedOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['title'] }}</option>
                    @endforeach
                </select>
                <x-icon-btn icon="add_link" tone="outline" size="sm" type="submit" show-label>{{ __('knowledge_pages.action.link') }}</x-icon-btn>
            </form>
        @endif
    </x-card>
@endif
