{{--
  Created on   : Sat Jul 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : validation-errors.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Einheitlicher Validierungsfehler-Block (Konsolidierung D4).
    Default: Liste aller Fehler (Markup der häufigsten Bestandsvariante).
    `first` → nur erste Meldung als Einzeiler; `tone` → error|warning.
    Abstände (mt-3/mb-4 …) via class-Attribut am Aufrufer.
--}}
@props([
    'first' => false,
    'tone' => 'error',
])
@php($alertTone = $tone === 'warning' ? 'alert alert-warning' : 'alert alert-error')
@if ($errors->any())
    @if ($first)
        <div {{ $attributes->merge(['class' => $alertTone . ' text-sm']) }} role="alert">{{ $errors->first() }}</div>
    @else
        <div {{ $attributes->merge(['class' => $alertTone . ' text-sm']) }} role="alert">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
@endif
