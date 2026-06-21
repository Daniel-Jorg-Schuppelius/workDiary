@props([
    'persons' => null,              // Array aus contact_persons (name/email/phone/primary)
    'heading' => null,              // optionale Überschrift; null ⇒ "Ansprechpartner"
])

{{--
    <x-contact-persons :persons="$customer->contact_persons" /> — Anzeige der
    strukturierten Ansprechpartner in Detail-/Show-Seiten. Leere Zeilen werden
    herausgefiltert; ohne Einträge wird nichts gerendert.
--}}

@php
    $list = is_array($persons)
        ? array_values(array_filter($persons, fn ($r) => is_array($r) && trim((string) ($r['name'] ?? '')) !== ''))
        : [];
    $heading = $heading ?? __('Ansprechpartner');
@endphp

@if ($list !== [])
    <div class="pt-3">
        <h3 class="mb-1 text-sm font-semibold">{{ $heading }}</h3>
        <ul class="divide-y divide-base-300 text-sm">
            @foreach ($list as $cp)
                <li class="py-2 flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span class="font-medium">{{ $cp['name'] ?? '' }}</span>
                    @if (! empty($cp['primary']))<x-status-badge tone="primary" size="xs">{{ __('Primär') }}</x-status-badge>@endif
                    @if (! empty($cp['email']))<a class="link link-hover" href="mailto:{{ $cp['email'] }}">{{ $cp['email'] }}</a>@endif
                    @if (! empty($cp['phone']))<span class="text-base-content/70">{{ $cp['phone'] }}</span>@endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
