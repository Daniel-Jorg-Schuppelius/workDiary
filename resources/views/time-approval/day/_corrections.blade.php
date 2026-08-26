{{--
  Created on   : Mon Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _corrections.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Tagesabschluss-Sektion „Korrekturanträge" (MVP-015 §5). Gemeinsamer Partial
  für Tagesabschluss- und „Heute"-Seite. Rendert nichts ohne Anträge.
  Erwartet aus dem Host-Scope: $correctionRequests, $closure.
--}}
@if ($correctionRequests->isNotEmpty())
    <x-card as="section">
        <h2 class="mb-3 flex items-center gap-2 text-base font-semibold">
            <x-icon name="rule" />
            {{ __('day-close.section.corrections') }}
        </h2>
        <ul class="space-y-3 text-sm">
            @foreach ($correctionRequests as $cr)
                <li class="flex flex-wrap items-start justify-between gap-2 rounded-box border border-base-300 p-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <x-status-badge :tone="$cr->status->tone()" size="sm">{{ $cr->status->label() }}</x-status-badge>
                            <span class="text-xs opacity-70">{{ $cr->created_at?->fdatetime() }} · {{ $cr->requestedBy?->name }}</span>
                        </div>
                        <p class="mt-1 whitespace-pre-line">{{ $cr->reason }}</p>
                        @if ($cr->decision_note)
                            <p class="mt-1 text-xs opacity-70">{{ __('day-close.field.decision') }}: {{ $cr->decision_note }} ({{ $cr->decidedBy?->name }})</p>
                        @endif
                    </div>
                    @if ($cr->isPending() && $closure->exists)
                        @can('approveCorrection', $closure)
                            <div class="flex gap-1">
                                <form method="POST" action="{{ route('day-close.correction.approve', $cr) }}">
                                    @csrf
                                    <x-icon-btn icon="check" tone="success" size="sm" type="submit"
                                                show-label>{{ __('day-close.action.approve') }}</x-icon-btn>
                                </form>
                                <form method="POST" action="{{ route('day-close.correction.reject', $cr) }}">
                                    @csrf
                                    <x-icon-btn icon="close" tone="warning" size="sm" type="submit"
                                                show-label>{{ __('day-close.action.reject') }}</x-icon-btn>
                                </form>
                            </div>
                        @endcan
                    @endif
                </li>
            @endforeach
        </ul>
    </x-card>
@endif
