@props([
    'value' => null,          // Carbon|DateTimeInterface|null – wird als UTC angenommen
    'format' => 'd.m.Y H:i',  // PHP-Datumsformat für die Ausgabe
    'placeholder' => '—',     // Anzeige, wenn $value null ist
    'translated' => false,    // true → translatedFormat() (lokalisierte Wochentage/Monate)
])

{{--
    <x-local-time> — einheitliche Anzeige eines Zeitpunkts in der aktiven
    Anzeige-Zeitzone (User-Override → Organisation → Fallback, siehe App\Support\Tz).

    Daten liegen in UTC vor; diese Komponente ist die einzige Stelle, an der für
    die Ausgabe umgerechnet wird. Beispiele:

        <x-local-time :value="$attendance->started_at" format="H:i" />
        <x-local-time :value="$entry->created_at" />
        <x-local-time :value="$day" format="l, d.m.Y" translated />
--}}

@php
    $carbon = $value instanceof \DateTimeInterface
        ? \Carbon\CarbonImmutable::instance(
            $value instanceof \Carbon\CarbonInterface ? $value->toDateTimeImmutable() : \DateTimeImmutable::createFromInterface($value)
        )->setTimezone(\App\Support\Tz::current())
        : null;
@endphp

@if ($carbon === null)
    <span {{ $attributes }}>{{ $placeholder }}</span>
@else
    <time datetime="{{ $carbon->toIso8601String() }}" {{ $attributes }}>{{ $translated ? $carbon->translatedFormat($format) : $carbon->format($format) }}</time>
@endif
