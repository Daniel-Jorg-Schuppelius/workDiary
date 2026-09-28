{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _header.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kopfzeile der Doku-Website: Titel und Sprachumschalter ($languages = Codes mit derselben Seite). --}}
<header class="site-header">
    <a class="site-brand" href="index.html">{{ __(':app-Dokumentation', ['app' => config('app.name', 'WorkDiary')]) }}</a>
    @if (count($languages) > 1)
        <nav class="site-language-switch" aria-label="{{ __('Sprache') }}">
            @foreach ($languages as $code)
                @if ($code === $locale)
                    <span aria-current="true">{{ strtoupper($code) }}</span>
                @else
                    <a href="../{{ $code }}/{{ $file }}" lang="{{ $code }}" hreflang="{{ $code }}">{{ strtoupper($code) }}</a>
                @endif
            @endforeach
        </nav>
    @endif
</header>
