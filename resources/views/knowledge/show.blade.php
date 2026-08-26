{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Artikelseite Wissensbasis (Feature 011): Problem, Lösung, Meta,
  Feedback, Problemhistorie (Verknüpfungen), Anhänge.
--}}

@extends('layouts.app')

@section('title', $article->title . ' — ' . __('knowledge.title.index'))
@section('nav-title', $article->title)

@section('content')
    <x-page-shell>
        {{-- ── Kopf ──────────────────────────────────────────────────────── --}}
        <x-card>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-2">
                    <h2 class="font-['Space_Grotesk'] text-xl font-bold">{{ $article->title }}</h2>
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <x-status-badge :tone="$article->status->tone()">{{ $article->status->label() }}</x-status-badge>
                        @if ($article->category)
                            <x-status-badge tone="ghost" outline>{{ $article->category }}</x-status-badge>
                        @endif
                        @foreach ($article->tags as $tag)
                            <x-status-badge tone="ghost" outline>{{ $tag->name }}</x-status-badge>
                        @endforeach
                    </div>
                    <p class="text-xs text-muted">
                        {{ __('knowledge.field.creator') }}: {{ optional($article->creator)->name ?? '—' }}
                        @if ($article->published_at)
                            · {{ __('knowledge.field.published_at') }}: {{ $article->published_at->fdatetime() }}
                        @endif
                        · {{ __('knowledge.field.updated_at') }}: {{ $article->updated_at?->fdatetime() ?? '—' }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @can('update', $article)
                        <x-icon-btn icon="edit" tone="outline" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('knowledge.edit', $article)"
                                    show-label>{{ __('knowledge.action.edit') }}</x-icon-btn>
                    @endcan
                    @if ($article->status !== \App\Enums\Knowledge\ArticleStatus::Published)
                        @can('publish', $article)
                            <form method="POST" action="{{ route('knowledge.publish', $article) }}">
                                @csrf
                                <x-icon-btn icon="publish" tone="success" size="sm" type="submit" show-label>{{ __('knowledge.action.publish') }}</x-icon-btn>
                            </form>
                        @endcan
                    @endif
                    @if ($article->status !== \App\Enums\Knowledge\ArticleStatus::Archived)
                        @can('archive', $article)
                            <x-action-form :action="route('knowledge.archive', $article)"
                                  data-confirm-title="{{ __('knowledge.action.archive') }}"
                                  :confirm="__('knowledge.confirm_archive')"
                                  confirm-icon="archive"
                                  confirm-tone="warning"
                                  :confirm-label="__('knowledge.action.archive')">
                                <x-icon-btn icon="archive" tone="warning" size="sm" type="submit" show-label>{{ __('knowledge.action.archive') }}</x-icon-btn>
                            </x-action-form>
                        @endcan
                    @endif
                    @can('delete', $article)
                        <x-action-form :action="route('knowledge.destroy', $article)" method="DELETE"
                              data-confirm-title="{{ __('knowledge.action.delete') }}"
                              :confirm="__('knowledge.confirm_delete')"
                              confirm-icon="delete"
                              confirm-tone="error"
                              :confirm-label="__('knowledge.action.delete')">
                            <x-icon-btn icon="delete" tone="error" size="sm" type="submit" :label="__('knowledge.action.delete')" />
                        </x-action-form>
                    @endcan
                    <x-icon-btn icon="arrow_back" size="sm" :href="route('knowledge.index')" show-label>{{ __('knowledge.action.back') }}</x-icon-btn>
                </div>
            </div>
        </x-card>

        {{-- ── Problem & Lösung ──────────────────────────────────────────── --}}
        <x-card as="section">
            <h3 class="mb-3 flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                <x-icon name="report_problem" class="text-warning" /> {{ __('knowledge.field.problem') }}
            </h3>
            <p class="whitespace-pre-wrap text-sm text-base-content/90">{{ $article->problem }}</p>
        </x-card>

        <x-card as="section">
            <h3 class="mb-3 flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                <x-icon name="lightbulb" class="text-success" /> {{ __('knowledge.field.solution') }}
            </h3>
            <p class="whitespace-pre-wrap text-sm text-base-content/90">{{ $article->solution }}</p>
        </x-card>

        {{-- ── Feedback ──────────────────────────────────────────────────── --}}
        @can('feedback', $article)
            <x-card as="section">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                        <x-icon name="how_to_vote" class="text-muted" /> {{ __('knowledge.feedback.title') }}
                    </h3>
                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('knowledge.feedback', $article) }}">
                            @csrf
                            <input type="hidden" name="value" value="helpful">
                            <x-icon-btn icon="thumb_up" size="sm" type="submit" show-label
                                        :tone="$ownFeedback?->helpful === true ? 'success' : 'outline'">
                                {{ __('knowledge.feedback.helpful') }} ({{ $article->helpful_count }})
                            </x-icon-btn>
                        </form>
                        <form method="POST" action="{{ route('knowledge.feedback', $article) }}">
                            @csrf
                            <input type="hidden" name="value" value="notHelpful">
                            <x-icon-btn icon="thumb_down" size="sm" type="submit" show-label
                                        :tone="$ownFeedback !== null && $ownFeedback->helpful === false ? 'error' : 'outline'">
                                {{ __('knowledge.feedback.not_helpful') }} ({{ $article->not_helpful_count }})
                            </x-icon-btn>
                        </form>
                    </div>
                </div>
                @if ($ownFeedback !== null)
                    <p class="mt-2 text-xs text-muted">{{ __('knowledge.feedback.already_voted') }}</p>
                @endif
            </x-card>
        @endcan

        {{-- ── Problemhistorie: Verknüpfungen ────────────────────────────── --}}
        <x-card as="section" id="knowledge-links">
            <h3 class="mb-3 flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                <x-icon name="link" class="text-muted" /> {{ __('knowledge.title.links') }}
                <span class="font-normal text-muted">({{ $article->links->count() }})</span>
            </h3>
            @if ($article->links->isEmpty())
                <x-empty-state compact icon="link"
                               :title="__('knowledge.title.links')"
                               :message="__('knowledge.empty_links')" />
            @else
                <ul class="space-y-2">
                    @foreach ($article->links as $link)
                        @php
                            $linkable = $link->linkable;
                            $name = $linkable?->getAttribute('title') ?? $linkable?->getAttribute('name') ?? ('#' . $link->linkable_id);
                        @endphp
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 p-3">
                            <div class="flex min-w-0 items-center gap-2">
                                <x-status-badge tone="ghost" outline>{{ $linkLabels[$link->id] ?? '—' }}</x-status-badge>
                                <span class="truncate text-sm font-medium">{{ $name }}</span>
                                <span class="text-xs text-muted">
                                    {{ optional($link->creator)->name ?? '—' }} · {{ $link->created_at?->fdate() ?? '—' }}
                                </span>
                            </div>
                            @can('link', $article)
                                <x-action-form :action="route('knowledge.links.destroy', [$article, $link])" method="DELETE"
                                      data-confirm-title="{{ __('knowledge.action.unlink') }}"
                                      :confirm="__('knowledge.confirm_unlink')"
                                      confirm-icon="link_off"
                                      confirm-tone="warning"
                                      :confirm-label="__('knowledge.action.unlink')">
                                    <x-icon-btn icon="link_off" tone="warning" size="xs" type="submit"
                                                :label="__('knowledge.action.unlink')" />
                                </x-action-form>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        {{-- ── Anhänge (Screenshots etc.) ────────────────────────────────── --}}
        @include('attachments._panel', ['parent' => $article, 'parentType' => 'knowledge'])
    </x-page-shell>
@endsection
