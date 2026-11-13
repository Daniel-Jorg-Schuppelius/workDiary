{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Wissensseite (Feature 191): gezeigte Fassung (Bearbeitende die aktuelle,
  alle anderen die freigegebene), Freigabe, Prüfung, Pflichtlektüre,
  „geändert seit Ihrem letzten Besuch“, Schritte einer Anleitung,
  Unterseiten, Verknüpfungen, Sammlungen, Verweise und Anhänge.
  Variablen: $page, $content, $html (sicher gerendertes Markdown), $steps,
  $changedSince, $children, $links, $readingRequired, $readingConfirmed,
  $glossary, $isEditor, $attachments.
--}}
@extends('layouts.app')

@section('title', $content['title'] . ' — ' . __('knowledge_pages.title'))
@section('nav-title', $content['title'])

@section('content')
@php
    $pending = $page->hasPendingVersion();
    $Status = \App\Enums\Knowledge\KnowledgePageStatus::class;
@endphp
<x-page-shell>
    <x-card>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 space-y-2">
                <p class="text-xs text-muted">
                    <a class="link link-hover" href="{{ route('knowledge.pages.index', ['space' => $page->space?->sqid]) }}">{{ $page->space?->title }}</a>
                    @if ($page->section)
                        › <a class="link link-hover" href="{{ route('knowledge.pages.index', ['space' => $page->space?->sqid, 'section' => $page->section->sqid]) }}">{{ $page->section->title }}</a>
                    @endif
                    @if ($page->parent)
                        › <a class="link link-hover" href="{{ route('knowledge.pages.show', $page->parent) }}">{{ $page->parent->title }}</a>
                    @endif
                </p>
                <h2 class="font-['Space_Grotesk'] text-xl font-bold">{{ $content['title'] }}</h2>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <x-status-badge :tone="$page->status->tone()">{{ $page->status->label() }}</x-status-badge>
                    <x-status-badge tone="ghost" outline :icon="$page->kind->icon()">{{ $page->kind->label() }}</x-status-badge>
                    @if ($page->is_emergency)
                        <x-status-badge tone="error" outline icon="emergency">{{ __('knowledge_pages.badge.emergency') }}</x-status-badge>
                    @endif
                    @if ($page->space?->is_restricted)
                        <x-status-badge tone="info" outline icon="lock">{{ __('knowledge_pages.space.restricted') }}</x-status-badge>
                    @endif
                    @foreach ($page->tags as $tag)
                        <x-status-badge tone="ghost" outline>{{ $tag->displayName() }}</x-status-badge>
                    @endforeach
                </div>
                <p class="text-xs text-muted" data-page-version>
                    {{ $content['version'] !== null ? __('knowledge_pages.version.label', ['version' => $content['version']]) : __('knowledge_pages.version.none') }}
                    @if ($content['approved_at'])
                        · {{ __('knowledge_pages.version.approved', ['date' => $content['approved_at']->fdate(), 'name' => $content['approver'] ?? '—']) }}
                    @endif
                    · {{ __('knowledge_pages.field.owner') }}: {{ $page->owner?->name ?? '—' }}
                    @if ($page->next_review_on)
                        · {{ __('knowledge_pages.field.next_review_on') }}: {{ $page->next_review_on->fdate() }}
                    @endif
                    @if ($page->valid_until)
                        · {{ __('knowledge_pages.field.valid_until') }}: {{ $page->valid_until->fdate() }}
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('update', $page)
                    <x-icon-btn icon="edit" tone="primary" size="sm" show-label data-entry-modal-trigger :href="route('knowledge.pages.edit', $page)">{{ __('knowledge_pages.action.edit') }}</x-icon-btn>
                @endcan
                @if ($pending && $page->status === $Status::Draft)
                    @can('approve', $page)
                        <form method="POST" action="{{ route('knowledge.pages.approve', $page) }}">
                            @csrf
                            <x-icon-btn icon="verified" tone="success" size="sm" type="submit" show-label>{{ __('knowledge_pages.action.approve') }}</x-icon-btn>
                        </form>
                    @endcan
                @endif
                @if ($page->isPublished())
                    @can('review', $page)
                        <form method="POST" action="{{ route('knowledge.pages.review', $page) }}">
                            @csrf
                            <x-icon-btn icon="fact_check" tone="outline" size="sm" type="submit" show-label>{{ __('knowledge_pages.action.review') }}</x-icon-btn>
                        </form>
                    @endcan
                @endif
                @if ($page->isGuide() && $steps !== [])
                    <x-icon-btn icon="play_circle" tone="outline" size="sm" show-label :href="route('knowledge.pages.play', $page)">{{ __('knowledge_pages.guide.play') }}</x-icon-btn>
                @endif
                <x-action-menu icon="more_horiz" :label="__('knowledge_pages.action.more')">
                    <x-icon-btn icon="picture_as_pdf" size="sm" show-label :href="route('knowledge.pages.pdf', $page)">{{ __('knowledge_pages.action.pdf') }}</x-icon-btn>
                    @can('update', $page)
                        <x-icon-btn icon="history" size="sm" show-label :href="route('knowledge.pages.versions', $page)">{{ __('knowledge_pages.action.versions') }}</x-icon-btn>
                        {{-- Unterseiten bis zur Baumtiefe (KnowledgePage::MAX_DEPTH = 3). --}}
                        @if ($page->parent === null || $page->parent->parent_id === null)
                            <x-icon-btn icon="subdirectory_arrow_right" size="sm" show-label data-entry-modal-trigger :href="route('knowledge.pages.create', ['parent' => $page->sqid])">{{ __('knowledge_pages.action.create_child') }}</x-icon-btn>
                        @endif
                    @endcan
                    @can('link', $page)
                        <x-icon-btn icon="add_link" size="sm" show-label data-entry-modal-trigger :href="route('knowledge.pages.links.create', $page)">{{ __('knowledge_pages.action.link') }}</x-icon-btn>
                    @endcan
                    @can('manage', $page)
                        <x-icon-btn icon="manage_accounts" size="sm" show-label data-entry-modal-trigger :href="route('knowledge.pages.responsibility', $page)">{{ __('knowledge_pages.action.responsibility') }}</x-icon-btn>
                        <x-icon-btn icon="menu_book" size="sm" show-label :href="route('knowledge.pages.readings', $page)">{{ __('knowledge_pages.action.readings') }}</x-icon-btn>
                    @endcan
                    @if ($page->status === $Status::Valid)
                        @can('approve', $page)
                            <x-action-form :action="route('knowledge.pages.outdate', $page)"
                                           :confirm="__('knowledge_pages.confirm.outdate')" confirm-icon="history_toggle_off" confirm-tone="warning"
                                           :confirm-label="__('knowledge_pages.action.outdate')">
                                <x-icon-btn icon="history_toggle_off" size="sm" type="submit" show-label>{{ __('knowledge_pages.action.outdate') }}</x-icon-btn>
                            </x-action-form>
                        @endcan
                    @endif
                    @can('delete', $page)
                        <x-action-form :action="route('knowledge.pages.destroy', $page)" method="DELETE"
                                       :confirm="__('knowledge_pages.confirm.trash', ['days' => \App\Models\Knowledge\KnowledgePage::TRASH_DAYS])" confirm-icon="delete" confirm-tone="error"
                                       :confirm-label="__('knowledge_pages.action.trash')">
                            <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('knowledge_pages.action.trash') }}</x-icon-btn>
                        </x-action-form>
                    @endcan
                </x-action-menu>
                <x-collection-add-button :item="$page" />
            </div>
        </div>
    </x-card>

    @if (! $content['current'])
        <div class="alert alert-info text-sm" role="status">
            <x-icon name="history" />
            <span>{{ $isEditor ? __('knowledge_pages.banner.old_version', ['version' => $content['version']]) : __('knowledge_pages.banner.reader_version', ['version' => $content['version']]) }}</span>
            @if ($isEditor)
                <x-icon-btn icon="arrow_forward" size="xs" tone="info" show-label :href="route('knowledge.pages.show', $page)">{{ __('knowledge_pages.banner.to_current') }}</x-icon-btn>
            @endif
        </div>
    @elseif ($isEditor && $pending)
        <div class="alert alert-warning text-sm" role="alert">
            <x-icon name="pending_actions" />
            <span>{{ $page->approved_version !== null ? __('knowledge_pages.banner.pending', ['approved' => $page->approved_version]) : __('knowledge_pages.banner.never_approved') }}</span>
        </div>
    @endif
    @if ($page->status === $Status::Outdated)
        <div class="alert alert-warning text-sm" role="alert">
            <x-icon name="history_toggle_off" />
            <span>{{ __('knowledge_pages.banner.outdated') }}</span>
        </div>
    @endif
    @if ($changedSince !== null)
        <div class="alert alert-info text-sm" role="status" data-changed-since>
            <x-icon name="update" />
            <span>{{ __('knowledge_pages.banner.changed_since', ['seen' => $changedSince, 'version' => $content['version']]) }}</span>
            @if ($isEditor)
                <x-icon-btn icon="compare_arrows" size="xs" tone="info" show-label :href="route('knowledge.pages.versions', ['page' => $page, 'from' => $changedSince, 'to' => $content['version']])">{{ __('knowledge_pages.banner.compare') }}</x-icon-btn>
            @endif
        </div>
    @endif

    @if ($readingRequired && $page->status === $Status::Valid)
        <x-card as="section" id="knowledge-reading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="flex items-center gap-2 text-sm">
                    <x-icon name="menu_book" class="text-primary" />
                    {{ $readingConfirmed ? __('knowledge_pages.reading.confirmed', ['version' => $page->approved_version]) : __('knowledge_pages.reading.required', ['version' => $page->approved_version]) }}
                </p>
                @unless ($readingConfirmed)
                    <form method="POST" action="{{ route('knowledge.pages.confirm-read', $page) }}">
                        @csrf
                        <x-icon-btn icon="task_alt" tone="primary" size="sm" type="submit" show-label>{{ __('knowledge_pages.reading.confirm') }}</x-icon-btn>
                    </form>
                @endunless
            </div>
        </x-card>
    @endif

    @if ($html !== '')
        <x-card as="section">
            {{-- Markdown über KnowledgeMarkdownRenderer: eingegebenes HTML wird escaped, unsichere Links entfernt. --}}
            <div class="help-article text-sm" data-knowledge-page-body>{!! $html !!}</div>
        </x-card>
    @elseif (! $page->isGuide())
        <x-card as="section">
            <x-empty-state compact icon="article" :title="__('knowledge_pages.empty.body')" />
        </x-card>
    @endif

    @if ($page->isGuide())
        <x-card as="section" :title="__('knowledge_pages.guide.steps')" icon="photo_library" :count="count($steps)">
            @if ($content['current'])
                @can('update', $page)
                    <x-slot:actions>
                        <x-icon-btn icon="add_photo_alternate" tone="primary" size="sm" show-label data-entry-modal-trigger :href="route('knowledge.pages.steps.create', $page)">{{ __('knowledge_pages.guide.add_step') }}</x-icon-btn>
                    </x-slot:actions>
                @endcan
            @endif
            @if ($steps === [])
                <x-empty-state compact icon="photo_library" :title="__('knowledge_pages.guide.empty')" :message="__('knowledge_pages.guide.empty_hint')" />
            @else
                @php $liveSteps = $content['current'] && $isEditor ? $page->steps()->get()->values() : collect(); @endphp
                <ol class="space-y-6" data-guide-steps>
                    @foreach ($steps as $index => $step)
                        <li class="space-y-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('knowledge_pages.guide.step_number', ['number' => $index + 1]) }}</h3>
                                @php $liveStep = $liveSteps->get($index); @endphp
                                @if ($liveStep !== null)
                                    @can('update', $page)
                                        <div class="flex flex-wrap items-center gap-1">
                                            <x-icon-btn icon="draw" tone="outline" size="xs" :href="route('knowledge.pages.steps.edit', [$page, $liveStep])" :label="__('knowledge_pages.guide.edit_step')" />
                                            @unless ($loop->first)
                                                <form method="POST" action="{{ route('knowledge.pages.steps.move', [$page, $liveStep]) }}">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="up">
                                                    <x-icon-btn icon="arrow_upward" tone="outline" size="xs" type="submit" :label="__('knowledge_pages.guide.move_up')" />
                                                </form>
                                            @endunless
                                            @unless ($loop->last)
                                                <form method="POST" action="{{ route('knowledge.pages.steps.move', [$page, $liveStep]) }}">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="down">
                                                    <x-icon-btn icon="arrow_downward" tone="outline" size="xs" type="submit" :label="__('knowledge_pages.guide.move_down')" />
                                                </form>
                                            @endunless
                                            <x-action-form :action="route('knowledge.pages.steps.destroy', [$page, $liveStep])" method="DELETE"
                                                           :confirm="__('knowledge_pages.confirm.remove_step')" confirm-icon="delete" confirm-tone="warning"
                                                           :confirm-label="__('knowledge_pages.guide.remove_step')">
                                                <x-icon-btn icon="delete" tone="outline" size="xs" type="submit" :label="__('knowledge_pages.guide.remove_step')" />
                                            </x-action-form>
                                        </div>
                                    @endcan
                                @endif
                            </div>
                            @include('knowledge-pages._step_image', ['image' => $step['image'], 'markers' => $step['markers'], 'alt' => __('knowledge_pages.guide.step_number', ['number' => $index + 1])])
                            @if ($step['body'])
                                <p class="whitespace-pre-line text-sm">{{ $step['body'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-card>
    @endif

    @if ($children->isNotEmpty())
        <x-card as="section" :title="__('knowledge_pages.children.title')" icon="subdirectory_arrow_right" :count="$children->count()">
            <ul class="space-y-1 text-sm" role="list">
                @foreach ($children as $child)
                    <li class="flex items-center gap-2">
                        <x-icon :name="$child->kind->icon()" class="text-muted" />
                        <a class="link link-hover" href="{{ route('knowledge.pages.show', $child) }}">{{ $child->title }}</a>
                        <x-status-badge :tone="$child->status->tone()" size="xs">{{ $child->status->label() }}</x-status-badge>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <x-card as="section" id="knowledge-page-links" :title="__('knowledge_pages.links.title')" icon="link" :count="count($links)">
        @can('link', $page)
            <x-slot:actions>
                <x-icon-btn icon="add_link" tone="outline" size="sm" show-label data-entry-modal-trigger :href="route('knowledge.pages.links.create', $page)">{{ __('knowledge_pages.action.link') }}</x-icon-btn>
            </x-slot:actions>
        @endcan
        @if ($links === [])
            <x-empty-state compact icon="link" :title="__('knowledge_pages.links.empty')" :message="__('knowledge_pages.links.hint')" />
        @else
            <ul class="space-y-2" role="list">
                @foreach ($links as $link)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 p-2 text-sm">
                        <span class="flex min-w-0 items-center gap-2">
                            <x-status-badge tone="ghost" outline :icon="$link['icon']">{{ __('knowledge_pages.links.type.' . $link['type']) }}</x-status-badge>
                            <a class="link link-hover truncate" href="{{ $link['url'] }}">{{ $link['title'] }}</a>
                        </span>
                        @can('link', $page)
                            <x-action-form :action="route('knowledge.pages.links.destroy', [$page, $link['reference']])" method="DELETE"
                                           :confirm="__('knowledge_pages.confirm.unlink')" confirm-icon="link_off" confirm-tone="warning"
                                           :confirm-label="__('knowledge_pages.action.unlink')">
                                <x-icon-btn icon="link_off" tone="outline" size="xs" type="submit" :label="__('knowledge_pages.action.unlink')" />
                            </x-action-form>
                        @endcan
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    @if ($glossary->isNotEmpty())
        <x-card as="section" :title="__('knowledge_pages.glossary.on_page')" icon="spellcheck" :count="$glossary->count()">
            <ul class="space-y-1 text-sm" role="list">
                @foreach ($glossary as $entry)
                    <li><span class="font-medium">{{ $entry->term }}</span>@if ($entry->synonyms) <span class="text-muted">({{ implode(', ', $entry->synonyms) }})</span>@endif @if ($entry->description) — {{ $entry->description }}@endif</li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <x-import-origin :subject="$page" />
    <x-content-references :subject="$page" />

    <x-attachments-section id="attachments" :attachments="$attachments" :can-upload="false" :can-delete="\Illuminate\Support\Facades\Gate::allows('update', $page)" details />
    @if ($attachments->contains(fn ($attachment) => $attachment->isImage()) && $isEditor)
        <p class="px-1 text-xs text-muted">{{ __('knowledge_pages.help.image_reference') }}</p>
    @endif
</x-page-shell>
@endsection
