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
                    <video class="w-full rounded-box border border-base-300" controls preload="metadata"
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
