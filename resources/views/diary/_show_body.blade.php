{{--
  Created on   : Sun May 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _show_body.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Diary Detail-Body (für Seite + Dialog). Erwartet: $diary, $legacyEntry (nullable), $isDialog (bool, optional) --}}
<?php $isDialog = $isDialog ?? false; ?>

<article>
    {{-- Im Dialog tragen Modal-Header (Status/Autor) + Footer (Aktionen) diese Zeile. --}}
    @unless ($isDialog)
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div class="flex flex-wrap items-center gap-3">
            <span @class([
                'badge badge-sm',
                'badge-success' => $diary->statusTone() === 'done',
                'badge-info' => $diary->statusTone() === 'progress',
                'badge-warning' => $diary->statusTone() === 'open',
                'badge-error' => $diary->statusTone() === 'alert',
                'badge-ghost' => $diary->statusTone() === 'neutral',
            ])>{{ $diary->statusLabel() }}</span>
            @php($dispatchStatus = app(\App\Services\Dispatch\DispatchStatusResolver::class)->resolve($diary))
            <span @class([
                'badge badge-sm badge-outline',
                'badge-success' => $dispatchStatus->tone() === 'done',
                'badge-info' => $dispatchStatus->tone() === 'progress',
                'badge-warning' => $dispatchStatus->tone() === 'open',
                'badge-ghost' => $dispatchStatus->tone() === 'neutral',
            ])>{{ __('dispatch.badge_prefix') }}: {{ $dispatchStatus->label() }}</span>
            <span class="text-sm text-base-content/70">{{ optional($diary->user)->name ?? '—' }}</span>
            @if ($diary->is_archived)
                <x-status-badge tone="neutral">{{ __('Archiviert') }}{{ $diary->archived_at ? ' · ' . $diary->archived_at->fdate() : '' }}</x-status-badge>
            @endif
            @if ($diary->legacy_id)
                <x-status-badge tone="warning" outline>
                    Legacy #{{ $diary->legacy_id }}
                </x-status-badge>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @include('diary._show_actions')
        </div>
    </div>
    @endunless

    {{-- Im Dialog: Autor + Archiviert-/Legacy-Hinweis kompakt (Status/Titel stehen im Header). --}}
    @if ($isDialog)
        <div class="mb-4 flex flex-wrap items-center gap-2 text-sm text-base-content/70">
            <span class="inline-flex items-center gap-1"><x-icon name="person" size="1rem" /> {{ optional($diary->user)->name ?? '—' }}</span>
            @if ($diary->is_archived)
                <x-status-badge tone="neutral">{{ __('Archiviert') }}{{ $diary->archived_at ? ' · ' . $diary->archived_at->fdate() : '' }}</x-status-badge>
            @endif
            @if ($diary->legacy_id)
                <x-status-badge tone="warning" outline>Legacy #{{ $diary->legacy_id }}</x-status-badge>
            @endif
        </div>
    @endif

    @include('diary._lifecycle_panel', ['isDialog' => $isDialog])

    @php($dataQualityGaps = $dataQualityGaps ?? [])
    @if (! empty($dataQualityGaps))
        @php($hasBlockingGap = collect($dataQualityGaps)->contains('blocking', true))
        <div class="mb-4 alert {{ $hasBlockingGap ? 'alert-warning' : 'alert-info' }} text-sm" role="status">
            <x-icon name="rule" />
            <div>
                <p class="font-semibold">{{ __('classification.dataquality.heading') }}</p>
                <div class="mt-1 flex flex-wrap gap-1.5">
                    @foreach ($dataQualityGaps as $gap)
                        <span class="badge badge-sm {{ $gap['blocking'] ? 'badge-warning' : 'badge-ghost' }}">
                            {{ __('classification.dataquality.missing', ['domain' => $gap['label']]) }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <h2 class="mb-4 font-['Space_Grotesk'] text-2xl font-bold text-base-content">{{ __('Inhalt') }}</h2>
    <div class="whitespace-pre-wrap leading-relaxed text-base-content">{{ $diary->content }}</div>

    @if ($diary->tags->isNotEmpty())
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($diary->tags as $tag)
                <a href="{{ route('diary.index', ['tag' => $tag->sqid]) }}"
                    class="badge badge-outline"
                    @if ($tag->color) style="border-color: {{ $tag->color }}; color: {{ $tag->color }};" @endif>
                    #{{ $tag->name }}
                </a>
            @endforeach
        </div>
    @endif

    @if ($diary->response)
        <div class="mt-6 rounded-box border border-info/30 bg-info/10 p-5">
            <p class="mb-3 text-xs uppercase tracking-[0.2em] text-base-content/65">{{ __('Rückmeldung') }}</p>
            <div class="whitespace-pre-wrap leading-relaxed text-base-content">{{ $diary->response }}</div>
        </div>
    @endif

    <div class="mt-6"><x-custom-fields-card :subject="$diary" /></div>

    <div class="mt-6 grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
        <div class="rounded-xl border border-base-300 bg-base-200 px-4 py-3">
            <p class="mb-1 text-xs text-muted">{{ __('Von') }}</p>
            <p class="text-base-content">{{ $diary->start_at?->fdatetime() ?? '—' }}</p>
        </div>
        <div class="rounded-xl border border-base-300 bg-base-200 px-4 py-3">
            <p class="mb-1 text-xs text-muted">{{ __('Bis') }}</p>
            <p class="text-base-content">{{ $diary->end_at?->fdatetime() ?? '—' }}</p>
        </div>
        {{-- Vom Auftrag in die Auswertung (MVP-807, Entscheid P2-06): nur wer die
             Analyse sehen darf (Tarif und Recht), bekommt den Link. Inline-Form wie im
             Rest der Datei — ein PHP-Block daneben bringt den Blade-Compiler aus dem Tritt. --}}
        @php($mayOpenReport = static fn (string $route): bool => app(\App\Services\Licensing\FeatureFlagResolver::class)->routeEnabled($route) && app(\App\Services\Navigation\NavGate::class)->mayAccess($route) && \App\Http\Controllers\Reporting\CustomerAnalysisReportController::mayViewTeamReports(auth()->user()))
        @if ($diary->customer)
            <div class="rounded-xl border border-base-300 bg-base-200 px-4 py-3">
                <p class="mb-1 text-xs text-muted">{{ __('Kunde') }}</p>
                <p class="text-base-content">{{ $diary->customer->name }}@if ($diary->customer->company) — {{ $diary->customer->company }}@endif</p>
                @if ($mayOpenReport('reports.customers'))
                    <a href="{{ route('reports.customers', ['customer' => $diary->customer->sqid]) }}" class="link link-hover mt-1 inline-flex items-center gap-1 text-xs">
                        <x-icon name="bar_chart" /> {{ __('Kundenanalyse') }}
                    </a>
                @endif
            </div>
        @endif
        {{-- Gegenstand des Auftrags (Feature 009; Vollaudit 2026-07, M5). --}}
        @if ($diary->asset)
            <div class="rounded-xl border border-base-300 bg-base-200 px-4 py-3">
                <p class="mb-1 text-xs text-muted">{{ __('Objekt/Asset') }}</p>
                <p class="text-base-content"><a href="{{ route('assets.show', $diary->asset) }}" class="link link-hover">{{ $diary->asset->name }}</a></p>
                @if ($mayOpenReport('reports.assets'))
                    {{-- Die Produktanalyse kennt keinen Einzel-Asset-Filter: sie zeigt das
                         Asset neben gleichartigen (Kategorie, Hersteller). --}}
                    <a href="{{ route('reports.assets', array_filter(['category_code' => $diary->asset->category_code, 'manufacturer' => $diary->asset->manufacturer])) }}" class="link link-hover mt-1 inline-flex items-center gap-1 text-xs">
                        <x-icon name="inventory_2" /> {{ __('Produktanalyse') }}
                    </a>
                @endif
            </div>
        @endif
        <div class="rounded-xl border border-base-300 bg-base-200 px-4 py-3">
            <p class="mb-1 text-xs text-muted">{{ __('Erstellt') }}</p>
            <p class="text-base-content">{{ $diary->created_at->fdatetime() }}</p>
        </div>
        <div class="rounded-xl border border-base-300 bg-base-200 px-4 py-3">
            <p class="mb-1 text-xs text-muted">{{ __('Geändert') }}</p>
            <p class="text-base-content">{{ $diary->updated_at->diffForHumans() }}</p>
        </div>
    </div>
</article>

@if (!empty($legacyEntry))
    <section class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
        <p class="mb-4 font-['Space_Grotesk'] font-semibold text-base-content">Legacy-Original (tagebuch #{{ $legacyEntry->id }})</p>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <p class="mb-2 text-xs text-muted">Inhalt (original)</p>
                <p class="text-sm leading-relaxed whitespace-pre-wrap text-base-content/80">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($legacyEntry->inhalt, 400) }}</p>
            </div>
            @if ($legacyEntry->antwort)
                <div>
                    <p class="mb-2 text-xs text-muted">Antwort (original)</p>
                    <p class="text-sm leading-relaxed whitespace-pre-wrap text-base-content/80">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($legacyEntry->antwort, 400) }}</p>
                </div>
            @endif
        </div>
        <p class="mt-4 text-xs text-muted">Autor: {{ optional($legacyEntry->author)->uname ?? '—' }} · gelesen={{ $legacyEntry->gelesen }}</p>
    </section>
@endif

<section id="comments" class="rounded-box border border-base-300 bg-base-100 p-6 shadow-xs">
    @include('comments._thread', [
        'parent' => $diary,
        'storeRoute' => route('diary.comments.store', $diary),
    ])
</section>

@include('communication-notes._panel', ['notable' => $diary, 'notableKind' => 'diary'])

@include('attachments._panel', ['parent' => $diary, 'parentType' => 'diary'])

@include('open-issues._panel', ['subject' => $diary, 'subjectKind' => 'diary'])

{{-- Formulare (Feature 032): ausgefüllte Formulare + „Formular ausfüllen" --}}
@include('forms._panel', ['subject' => $diary, 'subjectKind' => 'diary'])

{{-- Verwaltete Dokumente am Auftrag (MVP-818): der Auftrag war bis dahin der
     einzige erlaubte Träger ohne eigenes Panel — auffindbar nur über die Liste. --}}
@include('documents._panel', ['documentable' => $diary, 'documentableKind' => 'diary'])

{{-- VOB/B-Schreiben (Feature 062, MVP-728): Anzeige der Schreiben, deren Anlass dieser Eintrag ist --}}
@include('construction-notices._diary_panel', ['diary' => $diary])

{{-- Wissensbasis (Feature 011): verknüpfte Artikel + Vorschläge zum Auftrag --}}
@include('knowledge._context_card', ['subject' => $diary, 'subjectKind' => 'diary', 'texts' => [(string) $diary->title, (string) $diary->content]])

{{-- Rückverweise (MVP-811); Wissensverknüpfungen zeigt schon die Wissenskarte. --}}
<x-content-references :subject="$diary" :without-knowledge-links="true" />
