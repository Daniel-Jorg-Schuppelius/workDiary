{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  PDF einer Wissensseite bzw. des Notfallhandbuchs (Feature 191, MVP-1668):
  je Seite Bereich, Fassung, Freigabe, Verantwortung und Gültigkeit, dazu der
  Text (KnowledgeMarkdownRenderer, Bilder eingebettet) und die Schritte mit
  eingezeichneten Markierungen. Variablen: $entries, $handbookTitle|null,
  $printedAt.
--}}
<x-pdf-layout pdf-type="knowledge_page" :pdf-title="$handbookTitle ?? ($entries[0]['title'] ?? __('knowledge_pages.title'))">
    <style>
        .kp-entry + .kp-entry { page-break-before: always; }
        .kp-meta td { border: 0; padding: 1pt 6pt 1pt 0; font-size: 9pt; color: #333; }
        .kp-body h1, .kp-body h2, .kp-body h3 { border: 0; margin: 10pt 0 4pt 0; }
        .kp-body table td, .kp-body table th { border: 1px solid #bbb; }
        .kp-body pre, .kp-body code { font-family: DejaVu Sans Mono, monospace; font-size: 8.5pt; background: #f4f4f4; }
        .kp-body img { max-width: 100%; }
        .kp-body .alert { border: 1px solid #94a3b8; background: #f1f5f9; padding: 5pt 7pt; margin: 6pt 0; }
        .kp-body .alert-success { border-color: #16a34a; background: #f0fdf4; }
        .kp-body .alert-warning { border-color: #d97706; background: #fffbeb; }
        .kp-body details { border: 1px solid #cbd5e1; padding: 5pt 7pt; margin: 6pt 0; }
        .kp-body summary { font-weight: bold; }
        .kp-draft { color: #b45309; font-weight: bold; }
        .kp-step { margin: 10pt 0; }
        .kp-step img { max-width: 100%; border: 1px solid #ccc; }
    </style>

    @if ($handbookTitle !== null)
        <h1>{{ $handbookTitle }}</h1>
        <div class="meta">{{ __('knowledge_pages.pdf.printed_at', ['date' => $printedAt->format('d.m.Y H:i')]) }} · {{ __('knowledge_pages.pdf.handbook_note') }}</div>
        @if ($entries === [])
            <p>{{ __('knowledge_pages.pdf.handbook_empty') }}</p>
        @else
            <h2>{{ __('knowledge_pages.pdf.contents') }}</h2>
            <ol>
                @foreach ($entries as $entry)
                    <li>{{ $entry['title'] }} <span class="small">({{ $entry['page']->space?->title }}@if ($entry['page']->section) › {{ $entry['page']->section->title }}@endif, {{ __('knowledge_pages.version.label', ['version' => $entry['version']]) }})</span></li>
                @endforeach
            </ol>
            <div style="page-break-after: always;"></div>
        @endif
    @endif

    @foreach ($entries as $entry)
        <div class="kp-entry">
            <h1>{{ $entry['title'] }}</h1>
            <table class="kp-meta">
                <tr><td>{{ __('knowledge_pages.field.space') }}:</td><td>{{ $entry['page']->space?->title }}@if ($entry['page']->section) › {{ $entry['page']->section->title }}@endif</td></tr>
                <tr><td>{{ __('knowledge_pages.pdf.version') }}:</td><td>{{ $entry['version'] ?? '—' }}
                    @if ($entry['draft']) <span class="kp-draft">{{ __('knowledge_pages.pdf.draft') }}</span>@endif</td></tr>
                <tr><td>{{ __('knowledge_pages.pdf.approval') }}:</td><td>{{ $entry['approved_at'] !== null ? __('knowledge_pages.version.approved', ['date' => $entry['approved_at']->format('d.m.Y'), 'name' => $entry['approver'] ?? '—']) : '—' }}</td></tr>
                <tr><td>{{ __('knowledge_pages.field.owner') }}:</td><td>{{ $entry['page']->owner?->name ?? '—' }}</td></tr>
                @if ($entry['page']->valid_until)
                    <tr><td>{{ __('knowledge_pages.field.valid_until') }}:</td><td>{{ $entry['page']->valid_until->format('d.m.Y') }}</td></tr>
                @endif
                <tr><td>{{ __('knowledge_pages.pdf.printed') }}:</td><td>{{ $printedAt->format('d.m.Y H:i') }}</td></tr>
            </table>
            {{-- KnowledgeMarkdownRenderer: eingegebenes HTML escaped, unsichere Links entfernt, Bilder nur aus Anhängen. --}}
            <div class="kp-body">{!! $entry['html'] !!}</div>
            @foreach ($entry['steps'] as $step)
                <div class="kp-step">
                    <h3>{{ __('knowledge_pages.guide.step_number', ['number' => $step['number']]) }}</h3>
                    @if ($step['image'])
                        <img src="{{ $step['image'] }}" alt="">
                    @endif
                    @if ($step['body'])
                        <p>{!! nl2br(e($step['body'])) !!}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
</x-pdf-layout>
