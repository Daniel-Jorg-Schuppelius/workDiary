@extends('layouts.app')

@section('title', __('Projektplanung') . ' – ' . $project->name)
@section('nav-title', __('Projektplanung'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :title="$project->name" :subtitle="__('Zeitstrahl der Aufgaben (Start – Deadline)')">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('projects.show', $project)" show-label>{{ __('Zum Auftrag') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @php($t = $timeline)
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <div class="mb-2 flex items-center justify-between text-xs text-base-content/60">
                <span>{{ $t['from']->isoFormat('DD.MM.YYYY') }} – {{ $t['to']->isoFormat('DD.MM.YYYY') }}</span>
                <span class="hidden sm:inline">{{ __('Balken ziehen zum Verschieben · Ränder ziehen zum Verlängern') }}</span>
            </div>

            @if ($t['groups']->isEmpty())
                <x-empty-state compact
                    icon='<span class="material-symbols-outlined" aria-hidden="true">checklist</span>'
                    :title="__('Noch keine Aufgaben für diesen Auftrag.')" />
            @else
                {{-- Wochen-Achse --}}
                <div class="relative ml-44 h-6 border-b border-base-300">
                    @foreach ($t['weeks'] as $w)
                        <div class="absolute top-0 h-full border-l border-base-200 pl-1 text-[10px] text-base-content/50"
                             style="left: {{ $w['offsetPct'] }}%">{{ $w['label'] }}</div>
                    @endforeach
                    @if ($t['todayPct'] !== null)
                        <div class="absolute top-0 h-full border-l-2 border-error/70" style="left: {{ $t['todayPct'] }}%" title="{{ __('Heute') }}"></div>
                    @endif
                </div>

                {{-- Milestone-Marker --}}
                @if ($t['milestones']->isNotEmpty())
                    <div class="relative ml-44 h-5">
                        @foreach ($t['milestones'] as $m)
                            <div class="absolute top-0 -translate-x-1/2 text-[10px] text-warning"
                                 style="left: {{ $m['offsetPct'] }}%" title="{{ $m['milestone']->title }} · {{ \Illuminate\Support\Carbon::parse($m['milestone']->due_date)->fdate() }}">
                                <span class="material-symbols-outlined text-[14px]" aria-hidden="true">flag</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Gruppen je Bearbeiter --}}
                <div class="mt-2 space-y-4">
                    @foreach ($t['groups'] as $group)
                        <div>
                            <div class="mb-1 text-xs font-semibold text-base-content/70">{{ $group['label'] }}</div>
                            <div class="space-y-1">
                                @foreach ($group['tasks'] as $row)
                                    @php($overdue = $row['task']->due_date && \Illuminate\Support\Carbon::parse($row['task']->due_date)->isPast() && $row['task']->status?->value !== 'done')
                                    <div class="flex items-center gap-2">
                                        <div class="shrink-0 truncate pr-2 text-xs" style="width: 10.5rem"
                                             title="{{ $row['task']->title }}">{{ $row['task']->title }}</div>
                                        <div class="relative h-5 flex-1 rounded bg-base-200/60" data-track>
                                            @if ($row['dated'])
                                                <div data-bar
                                                     x-data="ganttBar({
                                                        offset: {{ $row['startOffsetDays'] }},
                                                        duration: {{ $row['durationDays'] }},
                                                        total: {{ $t['totalDays'] }},
                                                        fromIso: @js($t['fromIso']),
                                                        url: @js(route('projects.tasks.schedule', [$project, $row['task']])),
                                                        editable: {{ $row['editable'] ? 'true' : 'false' }},
                                                        color: @js($row['task']->color ?: ($overdue ? '#dc2626' : '#3b82f6')),
                                                     })"
                                                     class="group absolute top-0 flex h-5 items-center overflow-visible rounded text-[10px] text-white select-none"
                                                     :class="editable ? 'cursor-move' : ''"
                                                     :style="`left:${offsetPct}%; width:${widthPct}%; background-color:${color}`"
                                                     @pointerdown="startMove($event)"
                                                     :title="label">
                                                    <template x-if="editable">
                                                        <span class="absolute left-0 top-0 h-5 w-1.5 cursor-ew-resize rounded-l bg-black/20 opacity-0 group-hover:opacity-100"
                                                              @pointerdown.stop="startResize($event, 'l')"></span>
                                                    </template>
                                                    <span class="pointer-events-none truncate px-2" x-text="label"></span>
                                                    <template x-if="editable">
                                                        <span class="absolute right-0 top-0 h-5 w-1.5 cursor-ew-resize rounded-r bg-black/20 opacity-0 group-hover:opacity-100"
                                                              @pointerdown.stop="startResize($event, 'r')"></span>
                                                    </template>
                                                </div>
                                            @else
                                                <span class="absolute left-2 top-0 flex h-5 items-center text-[10px] italic text-base-content/50">{{ __('ohne Termin') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-page-shell>

@push('scripts')
<script>
    window.__ganttCsrf = @js(csrf_token());
    window.ganttBar = function (cfg) {
        return {
            offset: cfg.offset,
            duration: cfg.duration,
            total: cfg.total,
            fromIso: cfg.fromIso,
            url: cfg.url,
            editable: cfg.editable,
            color: cfg.color,
            _d: null,
            get offsetPct() { return Math.max(0, this.offset / this.total * 100); },
            get widthPct() { return Math.max(2, this.duration / this.total * 100); },
            get label() {
                const s = this.addDays(this.fromIso, this.offset);
                return this.fmt(s) + (this.duration > 0 ? '–' + this.fmt(this.addDays(this.fromIso, this.offset + this.duration)) : '');
            },
            _dayWidth(el) { const t = el.closest('[data-track]'); return Math.max(1, t.clientWidth / this.total); },
            startMove(e) { if (this.editable) this._begin(e, 'move'); },
            startResize(e, edge) { if (this.editable) this._begin(e, edge); },
            _begin(e, mode) {
                e.preventDefault();
                const bar = e.target.closest('[data-bar]');
                this._d = { mode, x: e.clientX, o: this.offset, du: this.duration, dw: this._dayWidth(bar) };
                const move = (ev) => this._move(ev);
                const up = () => { this._end(); window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); };
                window.addEventListener('pointermove', move);
                window.addEventListener('pointerup', up);
            },
            _move(e) {
                if (!this._d) return;
                const dd = Math.round((e.clientX - this._d.x) / this._d.dw);
                if (this._d.mode === 'move') {
                    this.offset = Math.max(0, this._d.o + dd);
                } else if (this._d.mode === 'l') {
                    const newOffset = Math.max(0, Math.min(this._d.o + dd, this._d.o + this._d.du));
                    this.duration = this._d.du + (this._d.o - newOffset);
                    this.offset = newOffset;
                } else {
                    this.duration = Math.max(0, this._d.du + dd);
                }
            },
            _end() {
                if (!this._d) return;
                const changed = this.offset !== this._d.o || this.duration !== this._d.du;
                this._d = null;
                if (changed) this.persist();
            },
            persist() {
                fetch(this.url, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.__ganttCsrf },
                    body: JSON.stringify({
                        start_date: this.addDays(this.fromIso, this.offset),
                        due_date: this.addDays(this.fromIso, this.offset + this.duration),
                    }),
                }).catch(() => {});
            },
            addDays(iso, days) { const d = new Date(iso + 'T00:00:00'); d.setDate(d.getDate() + days); return d.toISOString().slice(0, 10); },
            fmt(iso) { const p = iso.split('-'); return p[2] + '.' + p[1] + '.'; },
        };
    };
</script>
@endpush
@endsection
