{{-- Legacy-Diary Detail-Body (für Seite + Dialog). Erwartet: $entry, $isDialog (bool, optional) --}}
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

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <span class="badge badge-md {{ $badgeClass }}">{{ $entry->statusLabel() }}</span>
        <span class="text-base-content/40">|</span>
        <span class="text-base-content/70">{{ optional($entry->author)->uname ?? __('Unbekannt') }}</span>
        <span class="text-base-content/40">|</span>
        <span class="text-base-content/70">Legacy #{{ $entry->id }}</span>
    </div>
    @if (! $isDialog && ((int) $entry->user === (int) (Auth::user()->legacy_user_id ?? 0) || \App\Legacy\Support\LegacyRoleResolver::isAdmin(Auth::user())))
        <div class="flex items-center gap-2">
            <a href="{{ route('legacy.diary.edit', $entry) }}" data-entry-modal-trigger class="btn btn-sm btn-ghost">
                <x-icon name="edit" /> {{ __('Bearbeiten') }}
            </a>
            <form method="POST" action="{{ route('legacy.diary.destroy', $entry) }}" class="inline"
                data-confirm-dialog
                data-confirm-title="{{ __('Eintrag löschen') }}"
                data-confirm-message="{{ __('Legacy-Eintrag wirklich löschen?') }}"
                data-confirm-label="{{ __('Löschen') }}">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm btn-error btn-outline">
                    <x-icon name="delete" /> {{ __('Löschen') }}
                </button>
            </form>
        </div>
    @endif
</div>

<h2 class="mb-4 font-['Space_Grotesk'] text-2xl font-bold text-base-content">{{ __('Inhalt') }}</h2>
<div class="whitespace-pre-wrap leading-relaxed text-base-content">{{ $entry->inhalt }}</div>

@if ($entry->antwort)
    <div class="mt-6 rounded-box border border-info/30 bg-info/10 p-5">
        <p class="mb-3 text-xs uppercase tracking-[0.2em] text-base-content/65">{{ __('Rückmeldung') }}</p>
        <div class="whitespace-pre-wrap leading-relaxed text-base-content">{{ $entry->antwort }}</div>
    </div>
@endif

<div class="mt-6 grid grid-cols-2 gap-4 text-sm md:grid-cols-3">
    <div class="rounded-xl border border-base-300 bg-base-200 px-4 py-3">
        <p class="mb-1 text-xs text-base-content/60">{{ __('Von') }}</p>
        <p class="text-base-content">{{ $entry->von?->format('d.m.Y H:i') ?? '—' }}</p>
    </div>
    <div class="rounded-xl border border-base-300 bg-base-200 px-4 py-3">
        <p class="mb-1 text-xs text-base-content/60">{{ __('Bis') }}</p>
        <p class="text-base-content">{{ $entry->bis?->format('d.m.Y H:i') ?? '—' }}</p>
    </div>
    <div class="rounded-xl border border-base-300 bg-base-200 px-4 py-3">
        <p class="mb-1 text-xs text-base-content/60">{{ __('Aktuell') }}</p>
        <p class="text-base-content">{{ $entry->aktuell?->format('d.m.Y H:i') ?? '—' }}</p>
    </div>
</div>
