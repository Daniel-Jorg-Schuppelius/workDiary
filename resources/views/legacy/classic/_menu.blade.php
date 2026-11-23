{{--
  Created on   : Mon Nov 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _menu.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Knopfleiste der Altportale. Erwartet: $label, $rows (Zeilen aus Einträgen mit
    href, label, optional title, active, post — post sendet ein Formular, etwa zum Abmelden).
--}}
<nav class="kp-menu" aria-label="{{ $label }}">
    @foreach ($rows as $row)
        <ul>
            @foreach ($row as $item)
                <li>
                    @if ($item['post'] ?? false)
                        <form method="POST" action="{{ $item['href'] }}">
                            @csrf
                            <button type="submit" class="kp-linkbutton" @isset($item['title']) title="{{ $item['title'] }}" @endisset>{{ $item['label'] }}</button>
                        </form>
                    @else
                        <a href="{{ $item['href'] }}" @isset($item['title']) title="{{ $item['title'] }}" @endisset @if ($item['active'] ?? false) aria-current="page" @endif>{{ $item['label'] }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endforeach
</nav>
