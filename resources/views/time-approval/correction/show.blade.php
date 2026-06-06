@extends('layouts.app')

@section('title', __('Korrekturantrag #:id', ['id' => $request->id]))
@section('nav-title', __('Korrekturantrag #:id', ['id' => $request->id]))

@section('content')
    <x-index-page :subtitle="$request->user?->name . ' · ' . optional($request->scope_date)->format('d.m.Y')">
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" size="sm" tone="ghost"
                        :href="route('corrections.index')" show-label>{{ __('Zurück') }}</x-icon-btn>
        </x-slot:actions>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        <div class="card bg-base-200">
            <div class="card-body space-y-2">
                <div class="flex items-center gap-3">
                    <x-status-badge :tone="$request->status->tone()" size="md">{{ $request->status->label() }}</x-status-badge>
                    <span class="text-sm text-base-content/70">
                        {{ __('Antragsteller:in') }}: {{ $request->requestedBy?->name }}
                    </span>
                    @if ($request->decided_at)
                        <span class="text-sm text-base-content/70">
                            {{ __('Entschieden') }}: {{ $request->decided_at->orgTz()->format('d.m.Y H:i') }}
                            ({{ $request->decidedBy?->name }})
                        </span>
                    @endif
                </div>
                <div>
                    <div class="text-xs uppercase text-base-content/60">{{ __('Begründung') }}</div>
                    <div class="whitespace-pre-wrap">{{ $request->reason }}</div>
                </div>
                @if ($request->decision_note)
                    <div>
                        <div class="text-xs uppercase text-base-content/60">{{ __('Entscheidungs-Notiz') }}</div>
                        <div class="whitespace-pre-wrap">{{ $request->decision_note }}</div>
                    </div>
                @endif
            </div>
        </div>

        <h3 class="text-base font-semibold mt-4">{{ __('Items (:n)', ['n' => $request->items->count()]) }}</h3>
        @foreach ($request->items as $item)
            <div class="border border-base-300 rounded-md p-3 mb-2">
                <div class="text-xs text-base-content/60">
                    {{ class_basename($item->target_type) }} #{{ $item->target_id ?? '—' }} · {{ $item->action }}
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

        <div class="flex gap-2 mt-4">
            @can('submit', $request)
                <form method="POST" action="{{ route('corrections.submit', $request) }}">
                    @csrf
                    <button class="btn btn-sm btn-primary" type="submit">
                        <span class="material-symbols-outlined">send</span>{{ __('Einreichen') }}
                    </button>
                </form>
            @endcan
            @can('withdraw', $request)
                <form method="POST" action="{{ route('corrections.withdraw', $request) }}"
                      data-confirm-dialog
                      data-confirm-message="{{ __('Antrag wirklich zurückziehen?') }}"
                      data-confirm-icon="undo"
                      data-confirm-tone="warning"
                      data-confirm-label="{{ __('Zurückziehen') }}">
                    @csrf
                    <button class="btn btn-sm btn-ghost" type="submit">
                        <span class="material-symbols-outlined">undo</span>{{ __('Zurückziehen') }}
                    </button>
                </form>
            @endcan
        </div>
    </x-index-page>
@endsection
