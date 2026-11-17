{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : carry-forward-notes.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  <x-carry-forward-notes :subject="$model" /> — Merker für die nächste Periode
  (Feature 201, MVP-1645) an einem Vorkommen einer Reihe (Vertrag
  App\Models\Contracts\PeriodicOccurrence). Oben die offenen Merker aus
  früheren Perioden mit „Abhaken“, darunter „Merker fürs nächste Mal“.
  Lesen setzt `view`, Schreiben `update` am Vorkommen voraus. Ohne Reihe
  oder Periode kein Markup.
  Optional (MVP-1646, mehrere Vorkommen auf einer Seite, etwa die Pflichten
  eines Vertrags): `subtitle` benennt das Vorkommen, `anchor` ist die ID der Karte.
--}}
@props(['subject', 'subtitle' => null, 'anchor' => 'carry-forward'])

@php
    $cfPeriod = $subject instanceof \App\Models\Contracts\PeriodicOccurrence ? $subject->carryForwardPeriod() : null;
    $cfSeries = $subject instanceof \App\Models\Contracts\PeriodicOccurrence ? $subject->carryForwardSeries() : null;
    $cfVisible = $cfPeriod !== null && $cfSeries !== null && $cfSeries !== '' && auth()->check() && auth()->user()->can('view', $subject);
@endphp

@if ($cfVisible)
    @php
        $cfNotes = app(\App\Services\CarryForward\CarryForwardNotes::class)->forOccurrence($subject);
        $cfCanWrite = auth()->user()->can('update', $subject);
        $cfType = $subject->getMorphClass();
        $cfKey = (string) $subject->getRouteKey();
        $cfTargets = array_map(static fn (int $step): \App\Support\Period\PeriodKey => $cfPeriod->next($step), [1, 2, 3, 4]);
        // Eindeutige Feld-IDs, wenn eine Seite mehrere Vorkommen zeigt.
        $cfUid = preg_replace('/[^A-Za-z0-9_-]/', '', $cfType . '-' . $cfKey);
    @endphp
    <x-card as="section" :id="$anchor" :title="__('carry_forward.title')" :subtitle="$subtitle" icon="push_pin" :count="$cfNotes['open']->count() ?: null">
        @if ($cfNotes['open']->isEmpty() && $cfNotes['resolved']->isEmpty())
            <x-empty-state compact icon="push_pin" :title="__('carry_forward.empty.title')" :message="__('carry_forward.empty.message', ['period' => $cfPeriod->label()])" />
        @endif

        @if ($cfNotes['open']->isNotEmpty())
            <ul class="space-y-3" aria-label="{{ __('carry_forward.open_heading') }}">
                @foreach ($cfNotes['open'] as $cfNote)
                    <li class="rounded-box border border-warning/40 bg-warning/10 p-3">
                        <p class="whitespace-pre-line text-sm">{{ $cfNote->note }}</p>
                        <p class="mt-1 text-xs text-muted">
                            {{ __('carry_forward.left_by', [
                                'name' => $cfNote->creator?->name ?? '—',
                                'period' => $cfNote->origin_period_key?->label() ?? '—',
                                'from' => $cfNote->period_key?->label() ?? '—',
                            ]) }}
                        </p>
                        @if ($cfCanWrite)
                            <form method="POST" action="{{ route('carry-forward-notes.resolve', $cfNote) }}" class="mt-2 flex flex-wrap items-end gap-2">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="subject_type" value="{{ $cfType }}">
                                <input type="hidden" name="subject_id" value="{{ $cfKey }}">
                                <div class="min-w-48 flex-1">
                                    <label class="sr-only" for="cf-resolution-{{ $cfUid }}-{{ $cfNote->sqid }}">{{ __('carry_forward.resolution_label') }}</label>
                                    <input id="cf-resolution-{{ $cfUid }}-{{ $cfNote->sqid }}" type="text" name="resolution_note" maxlength="500"
                                           class="input input-sm input-bordered w-full"
                                           placeholder="{{ __('carry_forward.resolution_placeholder') }}">
                                </div>
                                <x-button type="submit" tone="success" size="sm" icon="check">{{ __('carry_forward.resolve') }}</x-button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($cfNotes['resolved']->isNotEmpty())
            <ul class="mt-3 space-y-2" aria-label="{{ __('carry_forward.resolved_heading') }}">
                @foreach ($cfNotes['resolved'] as $cfNote)
                    <li class="text-sm">
                        <x-icon name="task_alt" class="align-middle text-success" />
                        <span class="line-through opacity-70">{{ $cfNote->note }}</span>
                        <span class="block pl-6 text-xs text-muted">
                            {{ __('carry_forward.resolved_by', ['name' => $cfNote->resolver?->name ?? '—', 'date' => $cfNote->resolved_at?->fdate() ?? '—']) }}@if ($cfNote->resolution_note) — {{ $cfNote->resolution_note }}@endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($cfNotes['left']->isNotEmpty())
            <div class="mt-4">
                <h3 class="text-xs font-semibold uppercase text-muted">{{ __('carry_forward.left_heading') }}</h3>
                <ul class="mt-1 space-y-1">
                    @foreach ($cfNotes['left'] as $cfNote)
                        <li class="flex items-start gap-2 text-sm">
                            <x-icon name="schedule_send" class="mt-0.5 text-muted" />
                            <span class="flex-1">
                                <span class="whitespace-pre-line">{{ $cfNote->note }}</span>
                                <span class="block text-xs text-muted">{{ __('carry_forward.from_period', ['period' => $cfNote->period_key?->label() ?? '—']) }}</span>
                            </span>
                            @can('delete', $cfNote)
                                <x-action-form :action="route('carry-forward-notes.destroy', $cfNote)" method="DELETE"
                                               :confirm="__('carry_forward.withdraw_confirm')"
                                               :confirm-label="__('carry_forward.withdraw')">
                                    <x-icon-btn icon="delete" tone="error" type="submit" size="xs" :label="__('carry_forward.withdraw')" />
                                </x-action-form>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($cfCanWrite)
            <form method="POST" action="{{ route('carry-forward-notes.store') }}" class="mt-4 space-y-2 border-t border-base-300 pt-3">
                @csrf
                <input type="hidden" name="subject_type" value="{{ $cfType }}">
                <input type="hidden" name="subject_id" value="{{ $cfKey }}">
                <x-textarea-field :id="'cf-note-new-' . $cfUid" name="note" :label="__('carry_forward.new_label')" rows="2" required
                                  :hint="__('carry_forward.new_hint')" />
                <div class="flex flex-wrap items-end gap-2">
                    <x-select-field :id="'cf-period-new-' . $cfUid" name="period_key" :label="__('carry_forward.target_label')">
                        @foreach ($cfTargets as $cfTarget)
                            <option value="{{ $cfTarget->key() }}">{{ __('carry_forward.from_period', ['period' => $cfTarget->label()]) }}</option>
                        @endforeach
                    </x-select-field>
                    <x-button type="submit" tone="primary" size="sm" icon="push_pin">{{ __('carry_forward.create') }}</x-button>
                    {{-- Öffnet den Hilfe-Drawer (Topic carry-forward.notes). --}}
                    <button type="button" class="link link-primary text-xs" data-help-trigger data-help-topic="carry-forward.notes">
                        <x-icon name="help" class="align-text-bottom" /> {{ __('carry_forward.help') }}
                    </button>
                </div>
            </form>
        @endif
    </x-card>
@endif
