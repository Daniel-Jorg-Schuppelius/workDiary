{{--
  Created on   : Sun Oct 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _summary.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kopf und Positionen eines Korrekturantrags — eine Vorlage für die eigene
     Sicht und die Prüfung (Konsolidierungs-Audit 2026-10, k4-07). Die
     Aktionsleisten bleiben je Sicht. --}}
<div class="card bg-base-200">
    <div class="card-body space-y-2">
        <div class="flex items-center gap-3 flex-wrap">
            <x-status-badge :tone="$request->status->tone()" size="md">{{ $request->status->label() }}</x-status-badge>
            @if ($request->self_applied)
                {{-- Erklärt, warum ein Antrag ohne Prüfer genehmigt und angewendet ist. --}}
                <x-status-badge tone="warning" size="md">{{ __('selbst nachgetragen') }}</x-status-badge>
            @endif
            <span class="text-sm text-base-content/70">
                {{ __('Antragsteller:in') }}: {{ $request->requestedBy?->name }}
            </span>
            @if ($request->decided_at)
                <span class="text-sm text-base-content/70">
                    {{ __('Entschieden') }}: {{ $request->decided_at->fdatetime() }}
                    ({{ $request->decidedBy?->name }})
                </span>
            @endif
            @if ($request->applied_at)
                <span class="text-sm text-success">
                    {{ __('Angewendet') }}: {{ $request->applied_at->fdatetime() }}
                </span>
            @endif
        </div>
        <div>
            <div class="text-xs uppercase text-muted">{{ __('Begründung') }}</div>
            <div class="whitespace-pre-wrap">{{ $request->reason }}</div>
        </div>
        @if ($request->decision_note)
            <div>
                <div class="text-xs uppercase text-muted">{{ __('Entscheidungs-Notiz') }}</div>
                <div class="whitespace-pre-wrap">{{ $request->decision_note }}</div>
            </div>
        @endif
    </div>
</div>

<h3 class="text-base font-semibold mt-4">{{ __('Items (:n)', ['n' => $request->items->count()]) }}</h3>
@foreach ($request->items as $item)
    <div class="border border-base-300 rounded-md p-3 mb-2">
        <div class="text-xs text-muted">
            {{ \App\Support\EntityType::label($item->target_type) }} #{{ $item->target_id ?? '—' }} · {{ \App\Support\Trans::or('attendance.correction.action.' . $item->action, $item->action) }}
        </div>
        <div class="grid md:grid-cols-2 gap-3 mt-2">
            <div>
                <div class="text-xs uppercase text-error">{{ __('Vorher') }}</div>
                <pre class="bg-error/10 text-xs p-2 rounded overflow-x-auto">{{ json_encode($item->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '—' }}</pre>
            </div>
            <div>
                <div class="text-xs uppercase text-success">{{ __('Nachher') }}</div>
                <pre class="bg-success/10 text-xs p-2 rounded overflow-x-auto">{{ json_encode($item->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '—' }}</pre>
            </div>
        </div>
    </div>
@endforeach
