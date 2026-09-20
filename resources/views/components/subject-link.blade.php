{{--
  Created on   : Sat Sep 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : subject-link.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  <x-subject-link :for="$document" /> — die Zugehörigkeit eines Inhalts als
  verlinkte Kette „Kunde › Projekt › Träger" (MVP-818). Wahlweise mit einem
  fertig aufgelösten :subject. Ohne Bezug erscheint ein Gedankenstrich; das ist
  die Aussage, nicht eine Lücke.

  Damit die Kette nicht in N+1 läuft, lädt die aufrufende Liste die Relationen
  über ContentSubjectResolver::eagerLoad() mit.
--}}
@props(['for' => null, 'subject' => null])

@php
    $subjectResolver = app(\App\Services\Content\ContentSubjectResolver::class);
    $resolved = $subject ?? ($for !== null ? $subjectResolver->resolve($for) : null);
    $chain = $resolved?->chain() ?? [];
@endphp

@if ($chain === [])
    <span {{ $attributes->merge(['class' => 'text-muted']) }}>&mdash;</span>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex flex-wrap items-center gap-1']) }}>
        @foreach ($chain as $link)
            @php
                $linkLabel = $subjectResolver->label($link);
                $linkType = \App\Support\EntityType::label($link::class);
                $linkUrl = \App\Support\EntityUrl::for($link);
            @endphp
            @if (! $loop->first)
                <span class="text-muted" aria-hidden="true">&rsaquo;</span>
            @endif
            @if ($linkUrl !== null)
                <a href="{{ $linkUrl }}" class="link link-hover"
                   aria-label="{{ $linkType }}: {{ $linkLabel !== '' ? $linkLabel : $linkType }}">{{ $linkLabel !== '' ? $linkLabel : $linkType }}</a>
            @else
                <span>{{ $linkLabel !== '' ? $linkLabel : $linkType }}</span>
            @endif
        @endforeach
    </span>
@endif
