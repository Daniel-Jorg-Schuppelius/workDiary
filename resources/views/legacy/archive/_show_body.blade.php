{{-- Legacy-Archiv Detail-Body (read-only). Erwartet: $entry, $isDialog (bool, optional) --}}
@php
    $isDialog = $isDialog ?? false;
    $badgeClass = match ((int) $entry->gelesen) {
        -1 => 'badge-neutral',
        1  => 'badge-success',
        2  => 'badge-warning',
        3  => 'badge-error',
        default => 'badge-ghost',
    };
@endphp

<div class="mb-6 flex flex-wrap items-center gap-2 text-sm">
    <span class="badge badge-md {{ $badgeClass }}">{{ $entry->statusLabel() }}</span>
    <span class="text-base-content/40">|</span>
    <span class="text-base-content/70">{{ optional($entry->mitarbeiter)->uname ?? __('Unbekannt') }}</span>
    <span class="text-base-content/40">|</span>
    <span class="text-base-content/70">Legacy #{{ $entry->id }}</span>
    <span class="badge badge-sm badge-ghost ml-auto">{{ __('Archiv – nur lesen') }}</span>
</div>

<h2 class="mb-4 font-['Space_Grotesk'] text-2xl font-bold text-base-content">{{ __('Inhalt') }}</h2>
<div class="whitespace-pre-wrap leading-relaxed text-base-content">{{ $entry->inhalt }}</div>

@if (! empty($entry->antwort))
    <div class="mt-6 rounded-box border border-info/30 bg-info/10 p-5">
        <p class="mb-3 text-xs uppercase tracking-[0.2em] text-base-content/65">{{ __('Rückmeldung') }}</p>
        <div class="whitespace-pre-wrap leading-relaxed text-base-content">{{ $entry->antwort }}</div>
    </div>
@endif

<div class="mt-6 grid grid-cols-2 gap-4 text-sm md:grid-cols-3">
    <div class="rounded-xl border border-base-300 bg-base-200 px-4 py-3">
        <p class="mb-1 text-xs text-base-content/60">{{ __('Von') }}</p>
        <p class="text-base-content">{{ $entry->von?->fdatetime() ?? '—' }}</p>
    </div>
    <div class="rounded-xl border border-base-300 bg-base-200 px-4 py-3">
        <p class="mb-1 text-xs text-base-content/60">{{ __('Bis') }}</p>
        <p class="text-base-content">{{ $entry->bis?->fdatetime() ?? '—' }}</p>
    </div>
    <div class="rounded-xl border border-base-300 bg-base-200 px-4 py-3">
        <p class="mb-1 text-xs text-base-content/60">{{ __('Aktuell') }}</p>
        <p class="text-base-content">{{ $entry->aktuell?->fdatetime() ?? '—' }}</p>
    </div>
</div>
