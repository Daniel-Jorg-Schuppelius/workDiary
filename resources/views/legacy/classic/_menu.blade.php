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
    $variant 'header' setzt sie als zweite Zeile in die Kopfzeile der App (Zeilen nebeneinander,
    getrennt durch einen Strich); sonst steht sie im alten Stil auf der Seite.
--}}
@if (($variant ?? 'page') === 'header')
    <nav aria-label="{{ $label }}">
        <ul class="flex items-center gap-1 overflow-x-auto sm:flex-wrap sm:justify-center">
            @foreach ($rows as $row)
                @if (! $loop->first)
                    <li aria-hidden="true" class="mx-1 h-5 w-px shrink-0 bg-base-300"></li>
                @endif
                @foreach ($row as $item)
                    <li class="shrink-0">
                        @if ($item['post'] ?? false)
                            <form method="POST" action="{{ $item['href'] }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-ghost" @isset($item['title']) title="{{ $item['title'] }}" @endisset>{{ $item['label'] }}</button>
                            </form>
                        @else
                            <a href="{{ $item['href'] }}" class="btn btn-sm {{ ($item['active'] ?? false) ? 'btn-primary' : 'btn-ghost' }}" @isset($item['title']) title="{{ $item['title'] }}" @endisset @if ($item['active'] ?? false) aria-current="page" @endif>{{ $item['label'] }}</a>
                        @endif
                    </li>
                @endforeach
            @endforeach
        </ul>
    </nav>
@else
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
@endif
