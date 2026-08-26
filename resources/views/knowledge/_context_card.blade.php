{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _context_card.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kontext-Karte „Wissensbasis" (Feature 011, Problemhistorie) für
  Detailseiten von Auftrag/Asset: (a) bereits verknüpfte Artikel,
  (b) einfache Vorschläge (LIKE-/Tag-Scoring) mit „Verknüpfen" sowie
  „Artikel aus diesem Auftrag erstellen" (Modal vorbefüllt).

  Erwartet: $subject (Model), $subjectKind ('diary'|'asset'|'customer'|'protocol'),
            $texts (list<string> — Kontext-Texte für die Vorschläge)
--}}
@php
    $featuresKnowledge = app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.knowledge');
@endphp

@if ($featuresKnowledge && \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\KnowledgeArticle::class))
    @php
        $subjectSqid = \App\Support\Sqid::encode(get_class($subject), (int) $subject->getKey());
        $knowledgeLinks = \App\Models\KnowledgeArticleLink::query()
            ->where('linkable_type', $subject->getMorphClass())
            ->where('linkable_id', $subject->getKey())
            ->whereHas('article')
            ->with('article')
            ->get();
        $knowledgeSuggestions = app(\App\Services\Knowledge\KnowledgeArticleService::class)
            ->suggestFor($subject, $texts);
        $canCreateKnowledge = \Illuminate\Support\Facades\Gate::allows('create', \App\Models\KnowledgeArticle::class);
    @endphp

    <x-card as="section" id="knowledge-context" :title="__('knowledge.title.index')" icon="school" :count="$knowledgeLinks->count()">
        @if ($canCreateKnowledge)
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('knowledge.create', ['source_kind' => $subjectKind, 'source_id' => $subjectSqid])"
                            show-label>{{ __('knowledge.action.create_from_subject') }}</x-icon-btn>
            </x-slot:actions>
        @endif

        @if ($knowledgeLinks->isEmpty() && $knowledgeSuggestions->isEmpty())
            <x-empty-state compact icon="school"
                           :title="__('knowledge.title.index')"
                           :message="__('knowledge.empty_context')" />
        @endif

        @if ($knowledgeLinks->isNotEmpty())
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">{{ __('knowledge.title.linked') }}</h3>
            <ul class="mb-4 space-y-2">
                @foreach ($knowledgeLinks as $link)
                    @php $linkedArticle = $link->article; @endphp
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 p-3">
                        <div class="min-w-0">
                            <a href="{{ route('knowledge.show', $linkedArticle) }}" class="link link-hover text-sm font-medium">{{ $linkedArticle->title }}</a>
                            <p class="max-w-md truncate text-xs text-muted">{{ $linkedArticle->problem }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="flex items-center gap-1 text-xs text-success"><x-icon name="thumb_up" /> {{ $linkedArticle->helpful_count }}</span>
                            @can('link', $linkedArticle)
                                <x-action-form :action="route('knowledge.links.destroy', [$linkedArticle, $link])" method="DELETE"
                                      data-confirm-title="{{ __('knowledge.action.unlink') }}"
                                      :confirm="__('knowledge.confirm_unlink')"
                                      confirm-icon="link_off"
                                      confirm-tone="warning"
                                      :confirm-label="__('knowledge.action.unlink')">
                                    <x-icon-btn icon="link_off" tone="warning" size="xs" type="submit"
                                                :label="__('knowledge.action.unlink')" />
                                </x-action-form>
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($knowledgeSuggestions->isNotEmpty())
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">{{ __('knowledge.title.suggestions') }}</h3>
            <ul class="space-y-2">
                @foreach ($knowledgeSuggestions as $suggestion)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-dashed border-base-300 p-3">
                        <div class="min-w-0">
                            <a href="{{ route('knowledge.show', $suggestion) }}" class="link link-hover text-sm font-medium">{{ $suggestion->title }}</a>
                            <p class="max-w-md truncate text-xs text-muted">{{ $suggestion->problem }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="flex items-center gap-1 text-xs text-success"><x-icon name="thumb_up" /> {{ $suggestion->helpful_count }}</span>
                            @can('link', $suggestion)
                                <form method="POST" action="{{ route('knowledge.links.store', $suggestion) }}">
                                    @csrf
                                    <input type="hidden" name="subject_kind" value="{{ $subjectKind }}">
                                    <input type="hidden" name="subject_id" value="{{ $subjectSqid }}">
                                    <x-icon-btn icon="add_link" tone="outline" size="xs" type="submit" show-label>{{ __('knowledge.action.link') }}</x-icon-btn>
                                </form>
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
@endif
