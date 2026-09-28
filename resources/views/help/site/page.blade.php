{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : page.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Themenseite der Doku-Website (MVP-971): dasselbe beim Einlesen escapte
     Markdown-HTML wie im Hilfecenter, Bilder relativ zu media/. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    @include('help.site._head', ['pageTitle' => $topic['title'], 'base' => '../'])
</head>
<body>
    @include('help.site._header', ['file' => $topic['topic'] . '.html'])
    <main class="site-main site-article-layout">
        <article class="site-article">
            <nav class="site-breadcrumb" aria-label="{{ __('Pfadnavigation') }}">
                <a href="index.html">{{ __('Übersicht') }}</a> › <span>{{ $section }}</span>
            </nav>
            <h1>{{ $topic['title'] }}</h1>
            {!! $body !!}
        </article>
        <aside class="site-aside">
            @if (count($topic['headings']) >= 3)
                <nav aria-label="{{ __('Auf dieser Seite') }}">
                    <p class="site-aside-title">{{ __('Auf dieser Seite') }}</p>
                    <ul>
                        @foreach ($topic['headings'] as $heading)
                            <li><a href="#{{ $heading['anchor'] }}">{{ $heading['text'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
            @if ($related !== [])
                <p class="site-aside-title">{{ __('Verwandte Themen') }}</p>
                <ul>
                    @foreach ($related as $entry)
                        <li><a href="{{ $entry['topic'] }}.html">{{ $entry['title'] }}</a></li>
                    @endforeach
                </ul>
            @endif
        </aside>
    </main>
</body>
</html>
