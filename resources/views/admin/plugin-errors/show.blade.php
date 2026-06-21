@extends('layouts.app')

@section('title', __('Plugin-Fehler #:id', ['id' => $error->id]))
@section('nav-title', __('Plugin-Fehler'))

@section('content')
<x-index-page :subtitle="__(':plugin · :phase · :time', ['plugin' => $error->plugin_id, 'phase' => $error->phase, 'time' => $error->occurred_at->format('Y-m-d H:i:s')])">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                    :href="route('admin.plugin-errors.index')"
                    show-label>{{ __('Zurück') }}</x-icon-btn>
        @if (! $error->isAcknowledged())
            <form method="POST" action="{{ route('admin.plugin-errors.acknowledge', $error) }}">
                @csrf
                <x-button type="submit" tone="primary" size="sm" icon="done">{{ __('Als gesehen markieren') }}</x-button>
            </form>
        @endif
    </x-slot:actions>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
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
                    {{ $error->acknowledged_at?->format('Y-m-d H:i:s') }}
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
                <div class="text-xs uppercase text-base-content/60">{{ __('Stacktrace') }}</div>
                <pre class="text-xs bg-base-200 rounded p-2 overflow-auto max-h-150">{{ $error->trace }}</pre>
            </div>
        @endif
    </div>
</x-index-page>
@endsection
