@extends('layouts.app')
@section('title', __('Legacy Archiv') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Legacy') . ' / ' . __('Archiv'))

@section('content')
    @php($legacyUsers = collect($users ?? []))
    @if ($isAdmin)
        <div class="mb-3">
            <a href="{{ route('legacy.archive.week') }}" class="btn btn-xs btn-outline">{{ __('Archiv-Wochenansicht') }}</a>
        </div>
    @endif

    <form method="GET" action="{{ route('legacy.archive.index') }}" class="rounded-box border border-base-300 bg-base-200 p-4 mb-4">
        <div class="flex flex-wrap items-end gap-4">
            @if ($isAdmin)
                <div class="min-w-48">
                    <label class="label text-sm font-semibold pb-1">{{ __('Mitarbeiter') }}</label>
                    <select name="user" class="select select-bordered select-sm">
                        <option value="">{{ __('Alle') }}</option>
                        @foreach ($legacyUsers as $legacyUser)
                            <option value="{{ $legacyUser->id }}" @selected(($filters['user'] ?? '') == $legacyUser->id)>{{ $legacyUser->uname }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label class="label text-sm font-semibold pb-1">{{ __('Von') }}</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input input-bordered input-sm">
            </div>
            <div>
                <label class="label text-sm font-semibold pb-1">{{ __('Bis') }}</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input input-bordered input-sm">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('Filtern') }}</button>
            @if (array_filter($filters))
                <a href="{{ route('legacy.archive.index') }}" class="btn btn-ghost btn-sm">{{ __('Zurücksetzen') }}</a>
            @endif
        </div>
    </form>

    @if ($isAdmin)
        <form method="POST" action="{{ route('legacy.archive.run') }}" class="rounded-box border border-base-300 bg-base-200 p-4 mb-4">
            @csrf
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="label text-sm font-semibold pb-1">{{ __('Archiv bis') }}</label>
                    <select name="months" class="select select-bordered select-sm">
                        <option value="3">3 {{ __('Monate') }}</option>
                        <option value="6">6 {{ __('Monate') }}</option>
                        <option value="9">9 {{ __('Monate') }}</option>
                        <option value="12">12 {{ __('Monate') }}</option>
                    </select>
                </div>
                <div>
                    <label class="label text-sm font-semibold pb-1">{{ __('Mitarbeiter') }} ({{ __('Optional') }})</label>
                    <select name="user" class="select select-bordered select-sm">
                        <option value="">{{ __('Alle') }}</option>
                        @foreach ($legacyUsers as $legacyUser)
                            <option value="{{ $legacyUser->id }}">{{ $legacyUser->uname }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('{{ __('Archivierung wirklich starten?') }}')">{{ __('Archivierung starten') }}</button>
            </div>
        </form>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-box border border-base-300 bg-base-100 p-3">
            <p class="mb-2 text-sm font-semibold text-base-content">{{ __('Archiv') }} {{ __('Aufträge') }}</p>
            <div class="space-y-3">
                @forelse ($diaryEntries as $entry)
                    <article class="rounded-box border border-base-300 bg-base-200 p-2">
                        <p class="text-sm text-base-content/70">{{ optional($entry->mitarbeiter)->uname ?? 'Unbekannt' }}</p>
                        <p class="mt-1 text-sm text-base-content">{{ truncate($entry->inhalt ?? '', 90) }}</p>
                        <p class="mt-1 text-xs text-base-content/60">{{ __('Bis') }} {{ $entry->bis?->format('d.m.Y H:i') ?? '-' }}</p>
                    </article>
                @empty
                    <p class="text-sm text-base-content/60">{{ __('Keine Einträge.') }}</p>
                @endforelse
            </div>
            @if ($diaryEntries->hasPages())
                <div class="mt-4">{{ $diaryEntries->links('pagination::simple-tailwind') }}</div>
            @endif
        </section>

        <section class="rounded-box border border-base-300 bg-base-100 p-3">
            <p class="mb-2 text-sm font-semibold text-base-content">{{ __('Archiv') }} {{ __('Bereitschaft') }}</p>
            <div class="space-y-3">
                @forelse ($onCallEntries as $entry)
                    <article class="rounded-box border border-base-300 bg-base-200 p-2">
                        <p class="text-sm text-base-content/70">{{ optional($entry->mitarbeiter)->uname ?? 'Unbekannt' }}</p>
                        <p class="mt-1 text-sm text-base-content">{{ $entry->von?->format('d.m.Y') ?? '-' }} bis {{ $entry->bis?->format('d.m.Y') ?? '-' }}</p>
                    </article>
                @empty
                    <p class="text-sm text-base-content/60">{{ __('Keine Einträge.') }}</p>
                @endforelse
            </div>
            @if ($onCallEntries->hasPages())
                <div class="mt-4">{{ $onCallEntries->links('pagination::simple-tailwind') }}</div>
            @endif
        </section>

        <section class="rounded-box border border-base-300 bg-base-100 p-3">
            <p class="mb-2 text-sm font-semibold text-base-content">{{ __('Archiv') }} {{ __('Notdienst') }}</p>
            <div class="space-y-3">
                @forelse ($notdienstEntries as $entry)
                    <article class="rounded-box border border-base-300 bg-base-200 p-2">
                        <p class="text-sm text-base-content/70">{{ optional($entry->mitarbeiter)->uname ?? 'Unbekannt' }}</p>
                        <p class="mt-1 text-sm text-base-content">{{ $entry->von?->format('d.m.Y') ?? '-' }} bis {{ $entry->bis?->format('d.m.Y') ?? '-' }}</p>
                    </article>
                @empty
                    <p class="text-sm text-base-content/60">{{ __('Keine Einträge.') }}</p>
                @endforelse
            </div>
            @if ($notdienstEntries->hasPages())
                <div class="mt-4">{{ $notdienstEntries->links('pagination::simple-tailwind') }}</div>
            @endif
        </section>
    </div>
@endsection
