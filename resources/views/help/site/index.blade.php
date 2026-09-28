{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Übersicht je Sprache (MVP-971): Themenbereiche und Suche im Browser (search.js + search-index.js). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    @include('help.site._head', ['pageTitle' => __('Übersicht'), 'base' => '../'])
    <script src="search-index.js" defer></script>
    <script src="../search.js" defer></script>
</head>
<body>
    @include('help.site._header', ['file' => 'index.html'])
    <main class="site-main">
        <h1>{{ __('Wie können wir helfen?') }}</h1>
        <form class="site-search" role="search" data-site-search>
            <label for="site-search-input">{{ __('Hilfethemen durchsuchen') }}</label>
            <input type="search" id="site-search-input" autocomplete="off" data-site-search-input>
        </form>
        <section class="site-results" hidden data-site-results>
            <h2>{{ __('Treffer') }}</h2>
            <ul data-site-results-list></ul>
            <p hidden data-site-results-empty>{{ __('Keine passenden Hilfethemen gefunden.') }}</p>
        </section>
        <div class="site-sections" data-site-sections>
            @foreach ($sections as $section)
                <section class="site-section">
                    <h2>{{ $section['title'] }}</h2>
                    <p class="site-muted">{{ $section['description'] }}</p>
                    <ul>
                        @foreach ($section['topics'] as $entry)
                            <li><a href="{{ $entry['topic'] }}.html">{{ $entry['title'] }}</a></li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    </main>
</body>
</html>
