{{--
  Created on   : Sat Aug 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _blocks.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Darstellung der Inhaltsblöcke einer Lerneinheit (Feature 149, MVP-736).
  Ein Bauteil für den Player UND die Autoren-Vorschau — sonst sähe der Kurs
  beim Schreiben anders aus als beim Lernen.

  Blöcke sind strukturiert, nicht freies HTML: jeder Typ hat ein festes
  Feldbild, Text bleibt Text. Was hier fehlt, ist im Kurs unsichtbar —
  deshalb bekommt jeder Typ eine Darstellung.

  Erwartet: $blocks (Liste), $mediaUrl (Closure attachment_id → URL|null).
--}}
@php
    /** @var iterable<array<string, mixed>> $blocks */
    $mediaUrl ??= static fn (int $id): ?string => null;
    // Zustand und Ableitungen je Anhang; leer, wenn die Ansicht sie nicht
    // mitgibt (Autoren-Vorschau ohne Verarbeitung).
    $mediaState ??= [];
@endphp

@foreach ($blocks as $block)
    @php
        $kind = \App\Enums\Learning\LearningBlockKind::tryFrom($block['type'] ?? '');
        $attachmentId = isset($block['attachment_id']) ? (int) $block['attachment_id'] : null;
        $url = $attachmentId !== null ? $mediaUrl($attachmentId) : null;
    @endphp

    @switch($kind)
        @case(\App\Enums\Learning\LearningBlockKind::Heading)
            <h4 class="mt-4 text-sm font-semibold">{{ $block['text'] ?? '' }}</h4>
            @break

        @case(\App\Enums\Learning\LearningBlockKind::Text)
            <p class="mt-3 whitespace-pre-line text-sm text-base-content/80">{{ $block['text'] ?? '' }}</p>
            @break

        @case(\App\Enums\Learning\LearningBlockKind::Callout)
            <div class="alert alert-{{ $block['tone'] ?? 'info' }} mt-3 text-sm" role="note">
                <x-icon name="campaign" />
                <span class="whitespace-pre-line">{{ $block['text'] ?? '' }}</span>
            </div>
            @break

        @case(\App\Enums\Learning\LearningBlockKind::Checklist)
            <ul class="mt-3 space-y-1 text-sm text-base-content/80">
                @foreach ((array) ($block['items'] ?? []) as $item)
                    <li class="flex items-start gap-2">
                        <x-icon name="check_box_outline_blank" class="text-muted" />
                        <span>{{ $item }}</span>
                    </li>
                @endforeach
            </ul>
            @break

        @case(\App\Enums\Learning\LearningBlockKind::Image)
            <figure class="mt-3">
                @if ($url)
                    {{-- alt ist Pflicht im Editor: ohne ihn ist das Bild für
                         Menschen, die es nicht sehen können, nicht vorhanden. --}}
                    <img src="{{ $url }}" alt="{{ $block['alt'] ?? '' }}"
                         class="max-w-full rounded-box border border-base-300">
                @else
                    <div class="alert alert-warning text-sm" role="status">
                        <x-icon name="broken_image" />
                        <span>{{ __('learning.help.block_media_missing') }}</span>
                    </div>
                @endif
                @if (! empty($block['caption']))
                    <figcaption class="mt-1 text-xs text-muted">{{ $block['caption'] }}</figcaption>
                @endif
            </figure>
            @break

        @case(\App\Enums\Learning\LearningBlockKind::File)
            <p class="mt-3 text-sm">
                <x-icon name="attach_file" class="text-muted" />
                @if ($url)
                    <a class="link" href="{{ $url }}">{{ $block['caption'] ?? __('learning.field.file') }}</a>
                @else
                    <span class="text-muted">{{ __('learning.help.block_media_missing') }}</span>
                @endif
            </p>
            @break

        @case(\App\Enums\Learning\LearningBlockKind::Video)
            @php
                // Feature 150: gespielt wird die umgerechnete Fassung, nie das
                // Original — das kann HEVC/MOV sein und spielt dann nicht.
                $media = $attachmentId !== null ? ($mediaState[$attachmentId] ?? null) : null;
            @endphp
            <figure class="mt-3">
                @if ($media && ! $media['ready'])
                    {{-- Ein Kurs, dessen Video noch rechnet, muss das sagen —
                         sonst wirkt der Inhalt schlicht kaputt. --}}
                    <div class="alert alert-{{ $media['tone'] }} text-sm" role="status">
                        <x-icon name="movie" />
                        <span>{{ $media['failed'] ? ($media['error'] ?: __('media.errors.no_rendition')) : __('media.help.processing') }}</span>
                    </div>
                @elseif ($media['video'] ?? $url)
                    {{-- Autoplay nur stumm (Browser-Regel und Rücksicht); die
                         Position merkt sich der Browser je Block lokal (MVP-788). --}}
                    <video class="w-full rounded-box border border-base-300" controls preload="metadata"
                           @if (! empty($block['autoplay'])) autoplay muted playsinline @endif
                           @if (! empty($block['remember_position'])) data-remember-position="{{ 'lrn-video-' . md5((string) ($media['video'] ?? $url)) }}" @endif
                           @if ($media && $media['poster']) poster="{{ $media['poster'] }}" @endif
                           src="{{ $media['video'] ?? $url }}">
                        @foreach (($media['subtitles'] ?? []) as $track)
                            {{-- Eine noch nicht durchgesehene Maschinenspur trägt
                                 das im Namen: sie ist eine Hilfe, aber kein
                                 verlässlicher Text (WCAG 1.2.2). --}}
                            <track kind="subtitles" src="{{ $track['url'] }}" srclang="{{ $track['locale'] }}"
                                   label="{{ strtoupper($track['locale']) }}{{ ($track['machine'] ?? false) ? ' · ' . __('media.label.machine_short') : '' }}">
                        @endforeach
                    </video>
                @elseif (! empty($block['url']))
                    {{-- Externe Quelle: der Host steht in der frame-src-Allowlist
                         der Organisation, sonst blockt die CSP still. --}}
                    <iframe class="aspect-video w-full rounded-box border border-base-300"
                            src="{{ $block['url'] }}" title="{{ $block['caption'] ?? __('learning.field.video') }}"
                            referrerpolicy="no-referrer" allowfullscreen></iframe>
                @else
                    <div class="alert alert-warning text-sm" role="status">
                        <x-icon name="movie" />
                        <span>{{ __('learning.help.block_media_missing') }}</span>
                    </div>
                @endif
                @if (! empty($block['caption']))
                    <figcaption class="mt-1 text-xs text-muted">{{ $block['caption'] }}</figcaption>
                @endif
            </figure>
            @break

        @case(\App\Enums\Learning\LearningBlockKind::Embed)
            <figure class="mt-3">
                <iframe class="aspect-video w-full rounded-box border border-base-300"
                        src="{{ $block['url'] ?? '' }}" title="{{ $block['caption'] ?? __('learning.field.embed') }}"
                        referrerpolicy="no-referrer" allowfullscreen></iframe>
                @if (! empty($block['caption']))
                    <figcaption class="mt-1 text-xs text-muted">{{ $block['caption'] }}</figcaption>
                @endif
            </figure>
            @break

        @case(\App\Enums\Learning\LearningBlockKind::Gallery)
            {{-- Kein Karussell: ein Raster braucht kein Skript, ist per Tastatur
                 ohne Falle erreichbar und zeigt jedes Bild mit eigenem Alt-Text. --}}
            <figure class="mt-3" role="group" @if (! empty($block['caption'])) aria-label="{{ $block['caption'] }}" @endif>
                <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3" role="list">
                    @foreach ((array) ($block['images'] ?? []) as $image)
                        @php $imageUrl = isset($image['attachment_id']) ? $mediaUrl((int) $image['attachment_id']) : null; @endphp
                        <li>
                            @if ($imageUrl)
                                <img src="{{ $imageUrl }}" alt="{{ $image['alt'] ?? '' }}" loading="lazy"
                                     class="aspect-square w-full rounded-box border border-base-300 object-cover">
                            @else
                                <div class="flex aspect-square items-center justify-center rounded-box border border-dashed border-base-300 text-muted" role="img" aria-label="{{ __('learning.help.block_media_missing') }}">
                                    <x-icon name="broken_image" />
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if (! empty($block['caption']))
                    <figcaption class="mt-1 text-xs text-muted">{{ $block['caption'] }}</figcaption>
                @endif
            </figure>
            @break

        @case(\App\Enums\Learning\LearningBlockKind::Audio)
            <figure class="mt-3">
                @if ($url)
                    <audio class="w-full" controls preload="metadata" src="{{ $url }}"
                           @if (! empty($block['caption'])) aria-label="{{ $block['caption'] }}" @endif></audio>
                @else
                    <div class="alert alert-warning text-sm" role="status">
                        <x-icon name="headphones" />
                        <span>{{ __('learning.help.block_media_missing') }}</span>
                    </div>
                @endif
                @if (! empty($block['caption']))
                    <figcaption class="mt-1 text-xs text-muted">{{ $block['caption'] }}</figcaption>
                @endif
                {{-- Transkript ist Pflicht (WCAG 1.2.1): ohne ihn ist der Inhalt
                     für gehörlose Menschen nicht vorhanden. --}}
                @if (! empty($block['text']))
                    <details class="mt-2 text-sm">
                        <summary class="cursor-pointer text-muted">{{ __('learning.field.transcript') }}</summary>
                        <p class="mt-1 whitespace-pre-line text-base-content/80">{{ $block['text'] }}</p>
                    </details>
                @endif
            </figure>
            @break

        @case(\App\Enums\Learning\LearningBlockKind::Code)
            @php $codeLabel = __('learning.field.code_example') . (! empty($block['language']) ? ' (' . $block['language'] . ')' : ''); @endphp
            {{-- tabindex: breite Zeilen scrollen waagerecht — ohne Fokus kämen
                 Tastaturnutzende nicht an den abgeschnittenen Teil. translate="no",
                 damit die Browser-Übersetzung den Code nicht verändert. --}}
            <pre class="mt-3 overflow-x-auto rounded-box border border-base-300 bg-base-200 p-3 text-xs" tabindex="0"
                 role="region" aria-label="{{ $codeLabel }}" translate="no"><code>{{ $block['text'] ?? '' }}</code></pre>
            @break

        @case(\App\Enums\Learning\LearningBlockKind::Accordion)
            {{-- Native details/summary: Tastatur, Screenreader-Zustand und
                 CSP-Build funktionieren ohne eigenes Skript. --}}
            <div class="mt-3 space-y-1">
                @foreach ((array) ($block['sections'] ?? []) as $section)
                    <details class="collapse collapse-arrow rounded-box border border-base-300">
                        <summary class="collapse-title text-sm font-medium">{{ $section['title'] ?? '' }}</summary>
                        <div class="collapse-content">
                            <p class="whitespace-pre-line text-sm text-base-content/80">{{ $section['body'] ?? '' }}</p>
                        </div>
                    </details>
                @endforeach
            </div>
            @break

        @case(\App\Enums\Learning\LearningBlockKind::Table)
            @php $rows = array_values((array) ($block['rows'] ?? [])); $header = (array) ($rows[0] ?? []); @endphp
            <div class="mt-3 overflow-x-auto rounded-box border border-base-300" tabindex="0" role="region"
                 aria-label="{{ $block['caption'] ?? __('learning.field.block_table') }}">
                {{-- raw-table-ok: Autoreninhalt (Dokument), keine Liste — braucht caption und th scope. --}}
                <table class="table table-sm">
                    @if (! empty($block['caption']))
                        <caption class="caption-top p-2 text-left text-xs text-muted">{{ $block['caption'] }}</caption>
                    @endif
                    <thead>
                        <tr>
                            @foreach ($header as $cell)
                                <th scope="col">{{ $cell }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_slice($rows, 1) as $row)
                            <tr>
                                @foreach ((array) $row as $cell)
                                    <td class="whitespace-pre-line">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @break

        @case(\App\Enums\Learning\LearningBlockKind::Procedure)
            @php
                // Ein Lauf braucht einen Tätigkeitsbericht als Anker — im Kurs
                // zeigt der Block deshalb den gültigen Ablauf, gestartet wird
                // im Bericht.
                $procedure = \App\Models\ProcedureTemplate::query()->find($block['procedure_template_id'] ?? null);
                $procedureVersion = $procedure ? app(\App\Services\Procedure\ProcedureTemplateService::class)->currentVersionFor($procedure) : null;
                $procedureSteps = $procedureVersion ? $procedureVersion->steps()->orderBy('sort_order')->get(['label', 'description', 'required']) : collect();
            @endphp
            @if ($procedure)
                <section class="mt-3 rounded-box border border-base-300 p-3" aria-labelledby="procedure-block-{{ $loop->index }}">
                    <h4 id="procedure-block-{{ $loop->index }}" class="flex items-center gap-2 text-sm font-semibold">
                        <x-icon name="fact_check" class="text-muted" /> {{ $block['caption'] ?? $procedure->name }}
                    </h4>
                    @if ($procedure->description)
                        <p class="mt-1 whitespace-pre-line text-sm text-base-content/80">{{ $procedure->description }}</p>
                    @endif
                    @if ($procedureSteps->isNotEmpty())
                        <ol class="mt-2 list-decimal space-y-1 ps-5 text-sm">
                            @foreach ($procedureSteps as $step)
                                <li>
                                    <span class="font-medium">{{ $step->label }}</span>
                                    @unless ($step->required)
                                        <span class="text-xs text-muted">({{ __('learning.field.optional_step') }})</span>
                                    @endunless
                                    @if ($step->description)
                                        <span class="block whitespace-pre-line text-base-content/70">{{ $step->description }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="mt-2 text-xs text-muted">{{ __('learning.help.procedure_no_version') }}</p>
                    @endif
                    <p class="mt-2 text-xs text-muted">{{ __('learning.help.procedure_start_in_diary') }}</p>
                </section>
            @endif
            @break

        @case(\App\Enums\Learning\LearningBlockKind::Question)
            <section class="mt-3 rounded-box border border-info/40 bg-info/5 p-3" aria-labelledby="question-block-{{ $loop->index }}">
                <p id="question-block-{{ $loop->index }}" class="flex items-start gap-2 text-sm font-medium">
                    <x-icon name="quiz" class="text-info" /> <span class="whitespace-pre-line">{{ $block['text'] ?? '' }}</span>
                </p>
                @if (! empty($block['options']))
                    <ul class="mt-2 list-disc space-y-1 ps-5 text-sm text-base-content/80">
                        @foreach ((array) $block['options'] as $option)
                            <li>{{ $option['text'] ?? '' }}</li>
                        @endforeach
                    </ul>
                @endif
                {{-- Ohne Bewertung: die Auflösung liegt hinter einem Aufklapper,
                     damit man erst selbst überlegt. --}}
                <details class="mt-2 text-sm">
                    <summary class="cursor-pointer text-info">{{ __('learning.action.show_solution') }}</summary>
                    @php $correctOptions = array_filter((array) ($block['options'] ?? []), static fn ($o): bool => (bool) ($o['correct'] ?? false)); @endphp
                    @if ($correctOptions !== [])
                        <ul class="mt-1 space-y-1">
                            @foreach ($correctOptions as $option)
                                <li class="flex items-start gap-2"><x-icon name="check_circle" class="text-success" /> <span>{{ $option['text'] ?? '' }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                    @if (! empty($block['explanation']))
                        <p class="mt-1 whitespace-pre-line text-base-content/80">{{ $block['explanation'] }}</p>
                    @endif
                </details>
            </section>
            @break

        @case(\App\Enums\Learning\LearningBlockKind::Divider)
            <hr class="my-4 border-base-300">
            @break

        @case(\App\Enums\Learning\LearningBlockKind::Knowledge)
            @php $article = \App\Models\KnowledgeArticle::query()->find($block['knowledge_article_id'] ?? null); @endphp
            @if ($article)
                <p class="mt-3 text-sm">
                    <x-icon name="menu_book" class="text-muted" />
                    <a class="link" href="{{ route('knowledge.show', $article) }}">
                        {{ $block['caption'] ?: $article->title }}
                    </a>
                </p>
            @endif
            @break
    @endswitch
@endforeach
