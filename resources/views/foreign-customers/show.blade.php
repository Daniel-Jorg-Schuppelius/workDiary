@extends('layouts.app')
@section('title', $foreignCustomer->name . ' — ' . __('Fremdkunde'))
@section('nav-title', __('Fremdkunde'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        {{-- Kopf --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-block h-3 w-3 rounded-full" style="background:{{ $foreignCustomer->color ?: '#94a3b8' }}"></span>
                        <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ $foreignCustomer->name }}</h1>
                        @if ($foreignCustomer->isArchived())
                            <x-status-badge tone="ghost" size="sm">{{ __('archiviert') }}</x-status-badge>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-base-content/60">
                        {{ __('Endkunde von') }}
                        <a class="link" href="{{ route('customers.show', $foreignCustomer->customer) }}">{{ $foreignCustomer->customer->company ?: $foreignCustomer->customer->name }}</a>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @can('update', $foreignCustomer)
                        <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger
                                    :href="route('foreign-customers.edit', $foreignCustomer)" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                    @endcan
                    @can('promote', $foreignCustomer)
                        <x-action-form :action="route('foreign-customers.promote', $foreignCustomer)"
                              :confirm="__('Diesen Fremdkunden zu einem eigenständigen Kunden befördern? Alle zugeordneten Projekte werden auf den neuen Kunden umgehängt.')"
                              :confirm-label="__('Befördern')">
                            <x-icon-btn icon="upgrade" type="submit" size="sm" tone="primary" show-label>{{ __('Zu Kunde befördern') }}</x-icon-btn>
                        </x-action-form>
                    @endcan
                    @can('archive', $foreignCustomer)
                        @if ($foreignCustomer->isArchived())
                            <form method="POST" action="{{ route('foreign-customers.restore', $foreignCustomer) }}">
                                @csrf
                                <x-icon-btn icon="unarchive" type="submit" size="sm" show-label>{{ __('Wiederherstellen') }}</x-icon-btn>
                            </form>
                        @else
                            <form method="POST" action="{{ route('foreign-customers.archive', $foreignCustomer) }}">
                                @csrf
                                <x-icon-btn icon="archive" type="submit" size="sm" show-label>{{ __('Archivieren') }}</x-icon-btn>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>

            <div class="mt-4 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                @if ($foreignCustomer->company)<div><span class="text-base-content/50">{{ __('Firma') }}:</span> {{ $foreignCustomer->company }}</div>@endif
                @if ($foreignCustomer->contact_name)<div><span class="text-base-content/50">{{ __('Ansprechpartner') }}:</span> {{ $foreignCustomer->contact_name }}</div>@endif
                @if ($foreignCustomer->email)<div><span class="text-base-content/50">{{ __('E-Mail') }}:</span> {{ $foreignCustomer->email }}</div>@endif
                @if ($foreignCustomer->phone)<div><span class="text-base-content/50">{{ __('Telefon') }}:</span> {{ $foreignCustomer->phone }}</div>@endif
                @if ($foreignCustomer->address)<div class="sm:col-span-2"><span class="text-base-content/50">{{ __('Adresse') }}:</span> {{ $foreignCustomer->address }}</div>@endif
                @if ($foreignCustomer->comment)<div class="sm:col-span-2"><span class="text-base-content/50">{{ __('Notiz') }}:</span> {{ $foreignCustomer->comment }}</div>@endif
            </div>
        </div>

        {{-- Projekte --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('Projekte') }} ({{ $projects->count() }})</h2>
            @if ($projects->isEmpty())
                <p class="text-sm text-base-content/60">{{ __('Keine Projekte zugeordnet.') }}</p>
            @else
                <ul class="divide-y divide-base-200">
                    @foreach ($projects as $project)
                        <li class="flex items-center justify-between py-1.5 text-sm">
                            <a class="link link-hover" href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
                            <x-status-badge :tone="$project->statusTone()" size="xs">{{ $project->statusLabel() }}</x-status-badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Verlauf --}}
        @if ($auditLogs->isNotEmpty())
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('Verlauf') }}</h2>
                <ul class="space-y-1 text-xs text-base-content/60">
                    @foreach ($auditLogs as $log)
                        <li>{{ \Illuminate\Support\Carbon::parse($log->created_at)->isoFormat('L LT') }} · {{ $log->event }} · {{ $log->user?->name ?? '—' }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-page-shell>
@endsection
