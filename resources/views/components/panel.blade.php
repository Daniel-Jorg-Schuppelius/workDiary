@props(['tone' => 'surface'])
{{--
    Wiederverwendbares „Badge"-Panel mit Rundung. Teilt die Optik mit den
    Sidebars (siehe .wd-badge / .wd-surface in resources/css/app.css), damit
    sich Sidebars und Content-Flächen gleichen.

      tone="surface"  → helle Card-Fläche (Standard)
      tone="neutral"  → dunkles Anthrazit-Badge wie die Sidebars

    Zusätzliche Klassen/Attribute werden durchgereicht, z. B.
    <x-panel class="p-6">…</x-panel>.
--}}
<div {{ $attributes->class([
    'wd-surface' => $tone === 'surface',
    'wd-badge' => $tone === 'neutral',
]) }}>
    {{ $slot }}
</div>
