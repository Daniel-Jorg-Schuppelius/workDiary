@extends('layouts.app')
@section('title', __('Kanban') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Kanban'))
@section('wrapper-height-class', 'min-h-[calc(100dvh_-_var(--app-header-h))] lg:h-[calc(100dvh_-_var(--app-header-h))] lg:overflow-clip')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
@php
    $tabs = [
        'mine' => ['label' => __('Meine'), 'url' => route('kanban.index', ['scope' => 'mine'])],
        'team' => ['label' => __('Team'),  'url' => route('kanban.index', ['scope' => 'team'])],
    ];
    $activeTab = $teamScope ? 'team' : 'mine';
@endphp
<x-page-shell>
    {{-- Toolbar --}}
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Offene Auftragsbuch-Einträge nach Status visualisieren.')">
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('diary.create')"
                            show-label>{{ __('Eintrag') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    {{-- Tabs --}}
    @include('duties._tab_strip', ['tabs' => $tabs, 'tab' => $activeTab])

    @if ($isLimited)
        <div class="rounded-box border border-warning/40 bg-warning/10 px-4 py-3 text-sm text-base-content/80">
            {{ __('Es werden maximal :count Einträge angezeigt. Verfeinere den Zeitraum oder die Ansicht für vollständigere Ergebnisse.', ['count' => 200]) }}
        </div>
    @endif

    {{-- Board --}}
    <div class="grid min-h-0 flex-1 grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4"
         data-kanban-board
         data-csrf="{{ csrf_token() }}">
        @foreach ($columns as $statusCode => $col)
            @php $items = $byStatus->get($statusCode, collect()); @endphp
            <section class="flex min-h-0 flex-col rounded-box border border-base-300 bg-base-100 shadow-xs"
                     data-kanban-column data-status="{{ $statusCode }}">
                <header class="flex items-center justify-between border-b border-base-300 px-3 py-2">
                    <div class="flex items-center gap-2">
                        <span class="wd-week-legend wd-week-entry--{{ $col['tone'] }}"></span>
                        <span class="font-['Space_Grotesk'] font-semibold">{{ __($col['label']) }}</span>
                    </div>
                    <span class="badge badge-sm" data-kanban-count>{{ $items->count() }}</span>
                </header>
                <div class="flex min-h-0 flex-1 flex-col gap-2 overflow-auto p-2" data-kanban-list>
                    @foreach ($items as $entry)
                        @include('kanban._card', ['entry' => $entry])
                    @endforeach
                    <p class="px-2 py-4 text-center text-xs text-base-content/50 {{ $items->isEmpty() ? '' : 'hidden' }}" data-kanban-empty>{{ __('Keine Einträge') }}</p>
                </div>
            </section>
        @endforeach
    </div>
</x-page-shell>

<script>
(function () {
    const board = document.querySelector('[data-kanban-board]');
    if (!board) return;
    const csrf = board.dataset.csrf;
    let dragId = null;

    board.querySelectorAll('[data-kanban-card]').forEach(initCard);

    function initCard(card) {
        card.setAttribute('draggable', 'true');
        card.addEventListener('dragstart', (e) => {
            dragId = card.dataset.id;
            card.classList.add('opacity-50');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', dragId); } catch (_) {}
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('opacity-50');
            dragId = null;
        });
    }

    board.querySelectorAll('[data-kanban-column]').forEach((col) => {
        const list = col.querySelector('[data-kanban-list]');
        col.addEventListener('dragover', (e) => {
            if (!dragId) return;
            e.preventDefault();
            col.classList.add('ring-2', 'ring-primary');
        });
        col.addEventListener('dragleave', () => {
            col.classList.remove('ring-2', 'ring-primary');
        });
        col.addEventListener('drop', async (e) => {
            e.preventDefault();
            col.classList.remove('ring-2', 'ring-primary');
            const id = dragId;
            if (!id) return;
            const status = col.dataset.status;
            const card = board.querySelector(`[data-kanban-card][data-id="${id}"]`);
            if (!card) return;
            const sourceList = card.parentElement;
            list.appendChild(card);
            updateCounts();
            try {
                const res = await fetch(`/kanban/${id}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ status: parseInt(status, 10) }),
                });
                if (!res.ok) throw new Error('http ' + res.status);
            } catch (err) {
                // rollback
                if (sourceList) sourceList.appendChild(card);
                updateCounts();
                console.error(err);
            }
        });
    });

    function updateCounts() {
        board.querySelectorAll('[data-kanban-column]').forEach((col) => {
            const n = col.querySelectorAll('[data-kanban-card]').length;
            const badge = col.querySelector('[data-kanban-count]');
            if (badge) badge.textContent = n;
            const empty = col.querySelector('[data-kanban-empty]');
            if (empty) empty.classList.toggle('hidden', n > 0);
        });
    }
})();
</script>
@endsection
