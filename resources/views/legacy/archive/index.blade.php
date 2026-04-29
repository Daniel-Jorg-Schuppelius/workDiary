@extends('layouts.app')
@section('title', __('Legacy Archiv') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Archiv'))

@section('content')
<div class="flex h-[calc(100dvh-11rem)] flex-col gap-3">
    @if ($isAdmin)
        <div role="tablist" class="tabs tabs-box flex-none self-start">
            <a href="{{ route('legacy.archive.index') }}" class="tab tab-active">{{ __('Archivliste') }}</a>
            <a href="{{ route('legacy.archive.week') }}" class="tab">{{ __('Wochenansicht') }}</a>
        </div>
    @endif

    <form method="GET" action="{{ route('legacy.archive.index') }}" class="flex-none rounded-box border border-base-300 bg-base-200 p-4">
        <div class="flex flex-wrap items-end gap-4">
            @if ($isAdmin)
                <div class="flex flex-col min-w-48">
                    <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Mitarbeiter') }}</span></label>
                    <select name="user" class="select select-bordered select-sm w-full">
                        <option value="">{{ __('Alle') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(($filters['user'] ?? '') == $user->id)>{{ $user->uname }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="flex flex-col">
                <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Von') }} &ndash; {{ __('Bis') }}</span></label>
                <div class="join">
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="join-item input input-bordered input-sm" title="{{ __('Von') }}">
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="join-item input input-bordered input-sm" title="{{ __('Bis') }}">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('Filtern') }}</button>
            @if (array_filter($filters))
                <a href="{{ route('legacy.archive.index') }}" class="btn btn-ghost btn-sm">{{ __('Zurücksetzen') }}</a>
            @endif
        </div>
    </form>

    @if ($isAdmin)
        <form method="POST" action="{{ route('legacy.archive.run') }}" class="flex-none rounded-box border border-base-300 bg-base-200 p-4">
            @csrf
            <div class="flex flex-wrap items-end gap-4">
                <div class="flex flex-col">
                    <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Archiv bis') }}</span></label>
                    <select name="months" class="select select-bordered select-sm w-full">
                        <option value="3">3 {{ __('Monate') }}</option>
                        <option value="6">6 {{ __('Monate') }}</option>
                        <option value="9">9 {{ __('Monate') }}</option>
                        <option value="12">12 {{ __('Monate') }}</option>
                    </select>
                </div>
                <div class="flex flex-col min-w-48">
                    <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">Mitarbeiter (optional)</span></label>
                    <select name="user" class="select select-bordered select-sm w-full">
                        <option value="">{{ __('Alle') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->uname }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('{{ __('Archivierung wirklich starten?') }}')">{{ __('Archivierung starten') }}</button>
            </div>
        </form>
    @endif

    <div class="tabs tabs-box bg-base-200">
            <input type="radio" name="archiv_tabs" class="tab" aria-label="{{ __('Aufträge') }} ({{ $diaryEntries->total() }})" checked />
    <div class="tab-content bg-base-100 border-base-300 p-3">
        <div class="flex max-h-[calc(100dvh-32rem)] flex-col">
            <div class="min-h-0 flex-1 space-y-3 overflow-auto pr-1">
                @forelse ($diaryEntries as $entry)
                    <article class="cursor-pointer rounded-box border border-base-300 bg-base-200 p-2 transition hover:border-primary hover:bg-base-300" onclick="document.getElementById('archiv-diary-{{ $entry->id }}').showModal()">
                        <p class="text-sm text-base-content/70">{{ optional($entry->mitarbeiter)->uname ?? __('Unbekannt') }}</p>
                        <p class="mt-1 text-sm text-base-content">{{ \Illuminate\Support\Str::limit($entry->inhalt ?? '', 90) }}</p>
                        <p class="mt-1 text-xs text-base-content/60">{{ __('Bis') }} {{ $entry->bis?->format('d.m.Y H:i') ?? '-' }}</p>
                    </article>
                    @php
                        $statusMap = [
                            -1 => [__('Erledigt'), 'badge-neutral'],
                            1  => [__('Bestätigt'), 'badge-success'],
                            2  => [__('Offen'), 'badge-warning'],
                            3  => [__('Problem'), 'badge-error'],
                        ];
                        [$statusLabel, $statusBadge] = $statusMap[(int) $entry->gelesen] ?? [__('Unbekannt'), 'badge-ghost'];
                    @endphp
                    <dialog id="archiv-diary-{{ $entry->id }}" class="modal">
                        <div class="modal-box max-w-2xl p-0">
                            <header class="flex items-start justify-between gap-3 border-b border-base-300 bg-base-200/60 px-6 py-4">
                                <div class="min-w-0">
                                    <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Archiv-Auftrag') }}</p>
                                    <h3 class="font-['Space_Grotesk'] mt-1 truncate text-lg font-semibold">#{{ $entry->id }} &middot; {{ optional($entry->mitarbeiter)->uname ?? __('Unbekannt') }}</h3>
                                </div>
                                <div class="flex flex-none items-center gap-2">
                                    <span class="badge badge-sm {{ $statusBadge }}">{{ $statusLabel }}</span>
                                    <form method="dialog"><button class="btn btn-sm btn-square btn-error text-lg leading-none font-bold" aria-label="{{ __('Schließen') }}">&times;</button></form>
                                </div>
                            </header>
                            <div class="space-y-4 px-6 py-5 text-sm">
                                <dl class="grid grid-cols-3 gap-3">
                                    <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                        <dt class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Von') }}</dt>
                                        <dd class="mt-1 font-medium">{{ $entry->von?->format('d.m.Y H:i') ?? '-' }}</dd>
                                    </div>
                                    <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                        <dt class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Bis') }}</dt>
                                        <dd class="mt-1 font-medium">{{ $entry->bis?->format('d.m.Y H:i') ?? '-' }}</dd>
                                    </div>
                                    <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                        <dt class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Aktuell') }}</dt>
                                        <dd class="mt-1 font-medium">{{ $entry->aktuell?->format('d.m.Y H:i') ?? '-' }}</dd>
                                    </div>
                                </dl>
                                <section>
                                    <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-base-content/60">{{ __('Inhalt') }}</p>
                                    <p class="whitespace-pre-wrap rounded-box border border-base-300 bg-base-200/60 p-3 leading-relaxed">{{ $entry->inhalt ?? '-' }}</p>
                                </section>
                                @if (!empty($entry->antwort))
                                    <section>
                                        <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-base-content/60">{{ __('Antwort') }}</p>
                                        <p class="whitespace-pre-wrap rounded-box border border-success/40 bg-success/10 p-3 leading-relaxed">{{ $entry->antwort }}</p>
                                    </section>
                                @endif
                            </div>
                            <footer class="flex justify-end border-t border-base-300 bg-base-200/60 px-6 py-3">
                                <form method="dialog"><button class="btn btn-sm">{{ __('Schließen') }}</button></form>
                            </footer>
                        </div>
                        <form method="dialog" class="modal-backdrop"><button>close</button></form>
                    </dialog>
                @empty
                    <p class="text-sm text-base-content/60">{{ __('Keine Einträge.') }}</p>
                @endforelse
            </div>
            @if ($diaryEntries->hasPages())
                <div class="flex-none mt-3 border-t border-base-300 bg-base-100 px-2 pt-3">{{ $diaryEntries->links('vendor.pagination.daisyui-simple') }}</div>
            @endif
        </div>
        </div>

    <input type="radio" name="archiv_tabs" class="tab" aria-label="{{ __('Bereitschaft') }} ({{ $onCallEntries->total() }})" />
    <div class="tab-content bg-base-100 border-base-300 p-3">
        <div class="flex max-h-[calc(100dvh-32rem)] flex-col">
            <div class="min-h-0 flex-1 space-y-3 overflow-auto pr-1">
                @forelse ($onCallEntries as $entry)
                    <article class="cursor-pointer rounded-box border border-base-300 bg-base-200 p-2 transition hover:border-primary hover:bg-base-300" onclick="document.getElementById('archiv-oncall-{{ $entry->id }}').showModal()">
                        <p class="text-sm text-base-content/70">{{ optional($entry->mitarbeiter)->uname ?? __('Unbekannt') }}</p>
                        <p class="mt-1 text-sm text-base-content">{{ $entry->von?->format('d.m.Y') ?? '-' }} bis {{ $entry->bis?->format('d.m.Y') ?? '-' }}</p>
                    </article>
                    <dialog id="archiv-oncall-{{ $entry->id }}" class="modal">
                        <div class="modal-box max-w-md p-0">
                            <header class="flex items-start justify-between gap-3 border-b border-base-300 bg-base-200/60 px-6 py-4">
                                <div class="min-w-0">
                                    <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Archiv-Bereitschaft') }}</p>
                                    <h3 class="font-['Space_Grotesk'] mt-1 truncate text-lg font-semibold">#{{ $entry->id }} &middot; {{ optional($entry->mitarbeiter)->uname ?? __('Unbekannt') }}</h3>
                                </div>
                                <form method="dialog"><button class="btn btn-sm btn-square btn-error text-lg leading-none font-bold" aria-label="{{ __('Schließen') }}">&times;</button></form>
                            </header>
                            <div class="px-6 py-5 text-sm">
                                <dl class="grid grid-cols-2 gap-3">
                                    <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                        <dt class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Von') }}</dt>
                                        <dd class="mt-1 font-medium">{{ $entry->von?->format('d.m.Y') ?? '-' }}</dd>
                                    </div>
                                    <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                        <dt class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Bis') }}</dt>
                                        <dd class="mt-1 font-medium">{{ $entry->bis?->format('d.m.Y') ?? '-' }}</dd>
                                    </div>
                                </dl>
                            </div>
                            <footer class="flex justify-end border-t border-base-300 bg-base-200/60 px-6 py-3">
                                <form method="dialog"><button class="btn btn-sm">{{ __('Schließen') }}</button></form>
                            </footer>
                        </div>
                        <form method="dialog" class="modal-backdrop"><button>close</button></form>
                    </dialog>
                @empty
                    <p class="text-sm text-base-content/60">{{ __('Keine Einträge.') }}</p>
                @endforelse
            </div>
            @if ($onCallEntries->hasPages())
                <div class="flex-none mt-3 border-t border-base-300 bg-base-100 px-2 pt-3">{{ $onCallEntries->links('vendor.pagination.daisyui-simple') }}</div>
            @endif
        </div>
        </div>

    <input type="radio" name="archiv_tabs" class="tab" aria-label="{{ __('Notdienst') }} ({{ $notdienstEntries->total() }})" />
    <div class="tab-content bg-base-100 border-base-300 p-3">
        <div class="flex max-h-[calc(100dvh-32rem)] flex-col">
            <div class="min-h-0 flex-1 space-y-3 overflow-auto pr-1">
                @forelse ($notdienstEntries as $entry)
                    <article class="cursor-pointer rounded-box border border-base-300 bg-base-200 p-2 transition hover:border-primary hover:bg-base-300" onclick="document.getElementById('archiv-notdienst-{{ $entry->id }}').showModal()">
                        <p class="text-sm text-base-content/70">{{ optional($entry->mitarbeiter)->uname ?? __('Unbekannt') }}</p>
                        <p class="mt-1 text-sm text-base-content">{{ $entry->von?->format('d.m.Y') ?? '-' }} bis {{ $entry->bis?->format('d.m.Y') ?? '-' }}</p>
                    </article>
                    <dialog id="archiv-notdienst-{{ $entry->id }}" class="modal">
                        <div class="modal-box max-w-md p-0">
                            <header class="flex items-start justify-between gap-3 border-b border-base-300 bg-base-200/60 px-6 py-4">
                                <div class="min-w-0">
                                    <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Archiv-Notdienst') }}</p>
                                    <h3 class="font-['Space_Grotesk'] mt-1 truncate text-lg font-semibold">#{{ $entry->id }} &middot; {{ optional($entry->mitarbeiter)->uname ?? __('Unbekannt') }}</h3>
                                </div>
                                <form method="dialog"><button class="btn btn-sm btn-square btn-error text-lg leading-none font-bold" aria-label="{{ __('Schließen') }}">&times;</button></form>
                            </header>
                            <div class="px-6 py-5 text-sm">
                                <dl class="grid grid-cols-2 gap-3">
                                    <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                        <dt class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Von') }}</dt>
                                        <dd class="mt-1 font-medium">{{ $entry->von?->format('d.m.Y') ?? '-' }}</dd>
                                    </div>
                                    <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                        <dt class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Bis') }}</dt>
                                        <dd class="mt-1 font-medium">{{ $entry->bis?->format('d.m.Y') ?? '-' }}</dd>
                                    </div>
                                </dl>
                            </div>
                            <footer class="flex justify-end border-t border-base-300 bg-base-200/60 px-6 py-3">
                                <form method="dialog"><button class="btn btn-sm">{{ __('Schließen') }}</button></form>
                            </footer>
                        </div>
                        <form method="dialog" class="modal-backdrop"><button>close</button></form>
                    </dialog>
                @empty
                    <p class="text-sm text-base-content/60">{{ __('Keine Einträge.') }}</p>
                @endforelse
            </div>
            @if ($notdienstEntries->hasPages())
                <div class="flex-none mt-3 border-t border-base-300 bg-base-100 px-2 pt-3">{{ $notdienstEntries->links('vendor.pagination.daisyui-simple') }}</div>
            @endif
        </div>
        </div>
    </div>
</div>
@endsection
