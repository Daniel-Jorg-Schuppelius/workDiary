{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : content-references.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  <x-content-references :subject="$model" /> — Verweiskarte einer Detailseite
  (MVP-811): gesetzte Verweise mit „Verweis setzen“ (nur Inhaltstypen aus
  CollectableTypes) und „Hier erwähnt in“, gruppiert nach Typ. Seiten, die
  Wissensverknüpfungen schon in der Wissenskarte zeigen, blenden sie über
  :without-knowledge-links="true" aus. Ohne Verweise und ohne Recht erscheint nichts.
--}}
@props(['subject', 'withoutKnowledgeLinks' => false])

@php
    $referenceViewer = auth()->user();
    $referenceTypes = app(\App\Services\Collections\CollectableTypes::class);
    $referenceService = app(\App\Services\Collections\ContentReferenceService::class);
    $referenceKey = $referenceTypes->keyFor($subject);
    $mayReference = $referenceKey !== null && \Illuminate\Support\Facades\Gate::allows('create', \App\Models\Knowledge\ContentCollection::class);
    $referencesOut = $referenceViewer instanceof \App\Models\Platform\User && $referenceKey !== null ? $referenceService->outgoing($subject, $referenceViewer) : [];
    $referencesIn = $referenceViewer instanceof \App\Models\Platform\User ? $referenceService->backlinks($subject, $referenceViewer, (bool) $withoutKnowledgeLinks) : [];
@endphp

@if ($mayReference || $referencesOut !== [] || $referencesIn !== [])
    <x-card as="section" id="content-references" {{ $attributes }} :title="__('collections.references.title')" icon="link"
            :count="count($referencesOut) + array_sum(array_map(static fn (array $group): int => count($group['items']), $referencesIn))">
        @if ($mayReference)
            <x-slot:actions>
                <x-icon-btn icon="add_link" tone="outline" size="sm" show-label data-entry-modal-trigger
                            :href="route('references.create', ['type' => $referenceKey, 'item' => $subject->sqid])">{{ __('collections.references.action.create') }}</x-icon-btn>
            </x-slot:actions>
        @endif

        @if ($referencesOut === [] && $referencesIn === [])
            <x-empty-state compact icon="link" :title="__('collections.references.title')" :message="__('collections.references.empty')" />
        @endif

        @if ($referencesOut !== [])
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">{{ __('collections.references.outgoing') }}</h3>
            <ul class="mb-4 space-y-2">
                @foreach ($referencesOut as $row)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 p-3">
                        <div class="flex min-w-0 items-center gap-2">
                            <x-icon :name="$row['icon']" class="text-muted" />
                            <a href="{{ $row['url'] }}" class="link link-hover truncate text-sm font-medium">{{ $row['title'] }}</a>
                            <x-status-badge tone="ghost" outline>{{ $row['label'] }}</x-status-badge>
                            @if ($row['reference']->kind !== \App\Models\Knowledge\ContentReference::KIND_MENTIONED)
                                <x-status-badge tone="info" outline>{{ __('collections.references.kind.' . $row['reference']->kind) }}</x-status-badge>
                            @endif
                        </div>
                        @if ($mayReference && $row['reference']->kind === \App\Models\Knowledge\ContentReference::KIND_MENTIONED)
                            <x-action-form :action="route('references.destroy', $row['reference'])" method="DELETE"
                                           data-confirm-title="{{ __('collections.references.action.remove') }}"
                                           :confirm="__('collections.references.confirm_remove')"
                                           confirm-icon="link_off"
                                           confirm-tone="warning"
                                           :confirm-label="__('collections.references.action.remove')">
                                <x-icon-btn icon="link_off" tone="warning" size="xs" type="submit" :label="__('collections.references.action.remove')" />
                            </x-action-form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($referencesIn !== [])
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted">{{ __('collections.references.backlinks') }}</h3>
            <div class="space-y-3">
                @foreach ($referencesIn as $group)
                    <div>
                        <p class="mb-1 flex items-center gap-1 text-sm font-medium"><x-icon :name="$group['icon']" class="text-muted" /> {{ $group['label'] }} <span class="font-normal text-muted">({{ count($group['items']) }})</span></p>
                        <ul class="space-y-1">
                            @foreach ($group['items'] as $row)
                                <li class="flex flex-wrap items-center gap-2 text-sm">
                                    <a href="{{ $row['url'] }}" class="link link-hover font-medium">{{ $row['title'] }}</a>
                                    @if ($row['context'] !== null)
                                        <span class="text-xs text-muted">{{ $row['context'] }}</span>
                                    @endif
                                    @if ($row['kind'] !== \App\Models\Knowledge\ContentReference::KIND_MENTIONED)
                                        <x-status-badge tone="ghost" outline>{{ __('collections.references.kind.' . $row['kind']) }}</x-status-badge>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>
@endif
