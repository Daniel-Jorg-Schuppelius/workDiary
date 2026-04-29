@extends('layouts.app')
@section('title', Str::limit($entry->inhalt ?? '', 60) . ' — Legacy')
@section('nav-title', __('Eintrag') . ' #' . $entry->id)

@section('content')
    @php
        $badgeClass = match ((int) $entry->gelesen) {
            -1 => 'badge-neutral',
            1  => 'badge-success',
            2  => 'badge-warning',
            3  => 'badge-error',
            default => 'badge-ghost',
        };
        $weekAnchorDate = $entry->von ?? $entry->bis ?? $entry->aktuell;
        $weekDate = request()->query('week_date') ?: $weekAnchorDate?->format('o-\\WW');
        $listParams = [];
        if (preg_match('/^(\d{4})-W(\d{2})$/', (string) $weekDate, $matches) === 1) {
            $listMonday = now()->setISODate((int) $matches[1], (int) $matches[2], 1)->startOfDay();
            $listParams = [
                'from' => $listMonday->format('Y-m-d'),
                'to' => $listMonday->copy()->addDays(6)->format('Y-m-d'),
            ];
        }
    @endphp
    <div class="mx-auto flex h-[calc(100dvh-11rem)] w-full max-w-3xl flex-col gap-4">
        <div class="min-h-0 flex-1 overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-sm">
        <article class="h-full overflow-auto p-6 md:p-8">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="badge badge-md {{ $badgeClass }}">{{ $entry->statusLabel() }}</span>
                    <span class="text-base-content/40">|</span>
                    <span class="text-base-content/70">{{ optional($entry->author)->uname ?? __('Unbekannt') }}</span>
                    <span class="text-base-content/40">|</span>
                    <span class="text-base-content/70">Legacy #{{ $entry->id }}</span>
                </div>
                @if ((int) $entry->user === (int) (Auth::user()->legacy_user_id ?? 0) || \App\Support\LegacyRoleResolver::isAdmin(Auth::user()))
                    <div class="flex items-center gap-2">
                        <a href="{{ route('legacy.diary.edit', $entry) }}" class="btn btn-sm btn-ghost">{{ __('Bearbeiten') }}</a>
                        <form method="POST" action="{{ route('legacy.diary.destroy', $entry) }}" class="inline" onsubmit="return confirm('{{ __('Legacy-Eintrag wirklich löschen?') }}')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-error btn-outline">{{ __('Löschen') }}</button>
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
        </article>
        </div>

        <div class="flex-none flex flex-wrap items-center justify-center gap-3">
            <a href="{{ route('legacy.diary.index', $listParams) }}" class=" btn btn-sm btn-ghost">{{ __('Zurück zur Legacy-Liste') }}</a>
            <a href="{{ route('legacy.diary.week', array_filter(['week_date' => $weekDate])) }}" class=" btn btn-sm btn-primary">{{ __('Zur Wochenansicht') }}</a>
        </div>
    </div>
@endsection
