@extends('layouts.app')

@section('title', __('Korrekturantrag #:id', ['id' => $request->id]))
@section('nav-title', __('Korrekturantrag #:id', ['id' => $request->id]))

@section('content')
    <x-index-page :subtitle="$request->user?->name . ' · ' . optional($request->scope_date)->format('d.m.Y')">
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" size="sm" tone="ghost"
                        :href="route('admin.corrections.index')" show-label>{{ __('Zurück') }}</x-icon-btn>
        </x-slot:actions>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        <div class="card bg-base-200">
            <div class="card-body space-y-2">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="badge badge-{{ $request->status->tone() }}">{{ $request->status->label() }}</span>
                    <span class="text-sm text-base-content/70">
                        {{ __('Antragsteller:in') }}: {{ $request->requestedBy?->name }}
                    </span>
                    @if ($request->decided_at)
                        <span class="text-sm text-base-content/70">
                            {{ __('Entschieden') }}: {{ $request->decided_at->format('d.m.Y H:i') }}
                            ({{ $request->decidedBy?->name }})
                        </span>
                    @endif
                    @if ($request->applied_at)
                        <span class="text-sm text-success">
                            {{ __('Angewendet') }}: {{ $request->applied_at->format('d.m.Y H:i') }}
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

        <div class="flex flex-wrap gap-2 mt-4">
            @can('approve', $request)
                <form method="POST" action="{{ route('admin.corrections.approve', $request) }}" class="flex gap-2 items-end">
                    @csrf
                    <input type="text" name="note" class="input input-sm input-bordered"
                           placeholder="{{ __('Optionaler Vermerk') }}" maxlength="500" />
                    <button class="btn btn-sm btn-success" type="submit">
                        <span class="material-symbols-outlined">check</span>{{ __('Genehmigen') }}
                    </button>
                </form>
            @endcan
            @can('reject', $request)
                <form method="POST" action="{{ route('admin.corrections.reject', $request) }}" class="flex gap-2 items-end">
                    @csrf
                    <input type="text" name="note" class="input input-sm input-bordered w-72"
                           placeholder="{{ __('Begründung ≥ 20 Zeichen') }}" minlength="20" maxlength="2000" required />
                    <button class="btn btn-sm btn-error" type="submit">
                        <span class="material-symbols-outlined">close</span>{{ __('Ablehnen') }}
                    </button>
                </form>
            @endcan
            @can('apply', $request)
                <form method="POST" action="{{ route('admin.corrections.apply', $request) }}"
                      onsubmit="return confirm('{{ __('Antrag jetzt anwenden?') }}');">
                    @csrf
                    <button class="btn btn-sm btn-primary" type="submit">
                        <span class="material-symbols-outlined">play_arrow</span>{{ __('Anwenden') }}
                    </button>
                </form>
            @endcan
        </div>
    </x-index-page>
@endsection
