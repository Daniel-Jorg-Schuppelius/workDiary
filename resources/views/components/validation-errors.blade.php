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
