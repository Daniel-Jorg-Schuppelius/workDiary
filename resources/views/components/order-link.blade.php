{{--
  Created on   : Sun Oct 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : order-link.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'entry',             // DiaryEntry
    'params' => [],      // zusätzliche Query-Parameter
    'fragment' => null,  // Sprungmarke ohne #
    'as' => 'span',      // Element, wenn der Auftrag nicht geöffnet werden darf
    'linkClass' => '',   // Klassen nur für den Link (link, hover:…)
])

{{--
    <x-order-link> — Link auf die Auftragsdetails, nur wenn der Betrachter den
    Auftrag öffnen darf (DiaryEntryPolicy::view). Sonst steht derselbe Inhalt
    ohne Link da: Team-Ansichten zeigen fremde Aufträge, ohne ins 403 zu führen.
    Das Modell braucht `user_id`; Gate OrderLinkRuleTest.

        <x-order-link :entry="$entry" link-class="link">{{ $entry->title }}</x-order-link>
        <x-order-link :entry="$entry" as="div" data-entry-modal-trigger class="block">…</x-order-link>
--}}

@if ($entry !== null && \Illuminate\Support\Facades\Gate::allows('view', $entry))
    <a href="{{ route('diary.show', ['diary' => $entry] + $params) }}{{ $fragment ? '#' . $fragment : '' }}" {{ $attributes->class([$linkClass]) }}>{{ $slot }}</a>
@else
    <{{ $as }} {{ $attributes->except(['data-entry-modal-trigger']) }}>{{ $slot }}</{{ $as }}>
@endif
