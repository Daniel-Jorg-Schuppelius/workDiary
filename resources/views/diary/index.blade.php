@extends('layouts.app')
@section('title', 'Tagebuch — ' . config('app.name', 'WorkDiary'))
@section('nav-title', 'Alle Einträge')

@section('content')
<div class="flex h-[calc(100dvh-11rem)] flex-col gap-4">
    {{-- Filter-Leiste --}}
    <form method="GET" action="{{ route('diary.index') }}" class="flex-none rounded-box border border-base-300 bg-base-100 p-5 shadow-sm">
        <div class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-40">
                <label class="mb-2 block text-xs uppercase tracking-[0.2em] text-base-content/60">{{ __('Status') }}</label>
                <select name="status" class="select select-bordered select-sm w-full">
                    <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>{{ __('Alle') }}</option>
                    <option value="2" @selected(($filters['status'] ?? '') === '2')>{{ __('Offen') }}</option>
                    <option value="3" @selected(($filters['status'] ?? '') === '3')>{{ __('Problem') }}</option>
                    <option value="1" @selected(($filters['status'] ?? '') === '1')>{{ __('Bestätigt') }}</option>
                    <option value="-1" @selected(($filters['status'] ?? '') === '-1')>{{ __('Erledigt') }}</option>
                </select>
            </div>
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[0.2em] text-base-content/60">{{ __('Von') }}</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input input-bordered input-sm">
            </div>
            <div>
                <label class="mb-2 block text-xs uppercase tracking-[0.2em] text-base-content/60">{{ __('Bis') }}</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input input-bordered input-sm">
            </div>
            <div class="flex items-center gap-2 pb-2">
                <input type="checkbox" id="mine" name="mine" value="1" @checked(!empty($filters['mine'])) class="checkbox checkbox-primary checkbox-sm">
                <label for="mine" class="text-sm text-base-content/75">{{ __('Nur meine') }}</label>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('Filtern') }}</button>
            @if (array_filter($filters))
                <a href="{{ route('diary.index') }}" class="btn btn-ghost btn-sm">{{ __('Zurücksetzen') }}</a>
            @endif
        </div>
    </form>

    {{-- Zähler --}}
    <div class="flex-none grid gap-4 sm:grid-cols-4">
        @foreach ([['all',__('Gesamt'),'sky'], ['open',__('Offen'),'amber'], ['alert',__('Probleme'),'rose'], ['done',__('Erledigt'),'emerald']] as [$key, $label, $color])
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-sm">
                <p class="badge badge-sm {{ $key === 'open' ? 'badge-warning' : ($key === 'alert' ? 'badge-error' : ($key === 'done' ? 'badge-success' : 'badge-primary')) }}">{{ $label }}</p>
                <p class="mt-2 font-['Space_Grotesk'] text-3xl font-bold text-base-content">{{ number_format($counts[$key], 0, ',', '.') }}</p>
            </div>
        @endforeach
    </div>

    {{-- Eintrags-Liste --}}
    <div class="min-h-0 flex-1 overflow-y-auto pr-1 space-y-3">
        @forelse ($entries as $entry)
            <article class="grid gap-4 rounded-box border border-base-300 bg-base-100 p-4 shadow-sm transition hover:border-primary/30 md:grid-cols-[1fr_auto]">
                <div>
                    <div class="flex flex-wrap items-center gap-3 mb-3">
                        <span @class([
                            'badge badge-sm',
                            'badge-success' => $entry->statusTone() === 'done',
                            'badge-info' => $entry->statusTone() === 'progress',
                            'badge-warning' => $entry->statusTone() === 'open',
                            'badge-error' => $entry->statusTone() === 'alert',
                            'badge-ghost' => $entry->statusTone() === 'neutral',
                        ])>{{ $entry->statusLabel() }}</span>
                        <span class="text-sm text-base-content/70">{{ optional($entry->user)->name ?? '—' }}</span>
                    </div>
                    <p class="text-base leading-relaxed text-base-content">{{ Str::limit($entry->content, 160) }}</p>
                    <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-base-content/65">
                        @if ($entry->start_at)
                            <span>Von {{ $entry->start_at->format('d.m.Y H:i') }}</span>
                        @endif
                        @if ($entry->end_at)
                            <span>Bis {{ $entry->end_at->format('d.m.Y H:i') }}</span>
                        @endif
                        <span>Erstellt {{ $entry->created_at->diffForHumans() }}</span>
                    </div>
                </div>
                <div class="flex flex-col gap-2 md:items-end md:justify-between">
                    <a href="{{ route('diary.show', $entry) }}" class="btn btn-outline btn-primary btn-sm text-center">{{ __('Details') }}</a>
                    @can('update', $entry)
                        <a href="{{ route('diary.edit', $entry) }}" class="btn btn-ghost btn-sm text-center">{{ __('Bearbeiten') }}</a>
                    @endcan
                </div>
            </article>
        @empty
            <div class="rounded-box border border-dashed border-base-300 bg-base-100 p-8 text-center text-base-content/70">
                Keine Einträge gefunden.
                @if (array_filter($filters))
                    <a href="{{ route('diary.index') }}" class="ml-2 text-primary underline">{{ __('Filter zurücksetzen') }}</a>
                @endif
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if ($entries->hasPages())
        <div class="flex-none">
            {{ $entries->links('pagination::simple-tailwind') }}
        </div>
    @endif
</div>
@endsection
