{{--
  Created on   : Tue May 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Plugin-Fehler #:id', ['id' => $error->id]))
@section('nav-title', __('Plugin-Fehler'))

@section('content')
<x-index-page :subtitle="__(':plugin · :phase · :time', ['plugin' => $error->plugin_id, 'phase' => $error->phase, 'time' => $error->occurred_at->format('d.m.Y H:i')])">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                    :href="route('admin.plugin-errors.index')"
                    show-label>{{ __('Zurück') }}</x-icon-btn>
        @if ($newer)
            <x-icon-btn icon="chevron_left" tone="ghost" size="sm"
                        :href="route('admin.plugin-errors.show', $newer)" :label="__('Neuerer Fehler')" />
        @endif
        @if ($older)
            <x-icon-btn icon="chevron_right" tone="ghost" size="sm"
                        :href="route('admin.plugin-errors.show', $older)" :label="__('Älterer Fehler')" />
        @endif
        <x-icon-btn icon="settings" tone="ghost" size="sm"
                    :href="route('admin.plugins.index', ['q' => $error->plugin_id])"
                    show-label>{{ __('Zum Plugin') }}</x-icon-btn>
        <x-action-form :action="route('admin.plugins.reset-errors', $error->plugin_id)"
              :confirm="__('Failure-Counter zurücksetzen und offene Fehler dieses Plugins quittieren?')"
              confirm-tone="primary">
            <x-icon-btn type="submit" tone="warning" size="sm" icon="restart_alt" show-label>{{ __('Reset') }}</x-icon-btn>
        </x-action-form>
        <form method="POST" action="{{ route('admin.plugin-errors.reopen', $error) }}">
            @csrf
            <x-button type="submit" tone="ghost" size="sm" icon="undo">{{ __('Wieder öffnen') }}</x-button>
        </form>
    </x-slot:actions>

    <x-card class="space-y-3">
        <div class="flex flex-wrap gap-6">
            <div>
                <div class="text-xs uppercase text-base-content/60">{{ __('Organisation') }}</div>
                <div class="text-sm">{{ $error->organization?->name ?? __('global') }}</div>
            </div>
            @if ((int) $error->occurrences > 1)
                <div>
                    <div class="text-xs uppercase text-base-content/60">{{ __('Wiederholungen') }}</div>
                    <div class="text-sm tabular-nums">×{{ (int) $error->occurrences }}
                        @if ($error->last_occurred_at)
                            <span class="text-base-content/50">({{ __('zuletzt :time', ['time' => $error->last_occurred_at->diffForHumans()]) }})</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>
        <div>
            <div class="text-xs uppercase text-base-content/60">{{ __('Exception') }}</div>
            <div class="font-mono text-sm">{{ $error->exception_class }}</div>
        </div>
        <div>
            <div class="text-xs uppercase text-base-content/60">{{ __('Nachricht') }}</div>
            <div class="text-sm whitespace-pre-wrap">{{ $error->message }}</div>
        </div>
        @if ($error->isAcknowledged())
            <div>
                <div class="text-xs uppercase text-base-content/60">{{ __('Bestätigt') }}</div>
                <div class="text-sm">
                    {{ $error->acknowledged_at?->format('d.m.Y H:i') }}
                    @if ($error->acknowledger)
                        — {{ $error->acknowledger->name }}
                    @endif
                </div>
            </div>
        @endif
        @if (! empty($error->context))
            <div>
                <div class="text-xs uppercase text-base-content/60">{{ __('Kontext') }}</div>
                <pre class="text-xs bg-base-200 rounded p-2 overflow-auto">{{ json_encode($error->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        @endif
        @if (! empty($error->trace))
            <div>
                <div class="flex items-center justify-between">
                    <div class="text-xs uppercase text-base-content/60">{{ __('Stacktrace') }}</div>
                    <button type="button" class="btn btn-xs btn-ghost" data-copy-trace>{{ __('Kopieren') }}</button>
                </div>
                <pre class="text-xs bg-base-200 rounded p-2 overflow-auto max-h-150" data-trace-content>{{ $error->trace }}</pre>
            </div>
        @endif
    </x-card>
</x-index-page>

@push('scripts')
<script @cspNonce>
(function () {
    var button = document.querySelector('[data-copy-trace]');
    var content = document.querySelector('[data-trace-content]');
    if (!button || !content || !navigator.clipboard) return;
    button.addEventListener('click', function () {
        navigator.clipboard.writeText(content.textContent || '').then(function () {
            button.textContent = '{{ __('Kopiert!') }}';
            setTimeout(function () { button.textContent = '{{ __('Kopieren') }}'; }, 1500);
        });
    });
})();
</script>
@endpush
@endsection
