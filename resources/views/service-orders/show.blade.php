@extends('layouts.app')

@section('title', $order->title)

@section('content')
    <div class="w-full space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ $order->title }}</h1>
                <div class="text-sm text-base-content/60">
                    {{ $order->scheduled_for?->format('d.m.Y') }} ·
                    <span class="badge badge-ghost badge-sm">{{ __($order->status) }}</span>
                    <span class="badge badge-outline badge-sm">{{ __($order->priority) }}</span>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('service-orders.edit', $order) }}" data-entry-modal-trigger class="btn btn-sm">{{ __('Bearbeiten') }}</a>
                <a href="{{ route('service-orders.index') }}" class="btn btn-sm btn-ghost">{{ __('Zurück') }}</a>
            </div>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <dl class="grid gap-y-2 text-sm md:grid-cols-2">
                <dt class="text-base-content/60">{{ __('Kunde') }}</dt><dd>{{ $order->customer?->name ?? '—' }}</dd>
                <dt class="text-base-content/60">{{ __('Projekt') }}</dt><dd>{{ $order->project?->name ?? '—' }}</dd>
                <dt class="text-base-content/60">{{ __('Mitarbeiter') }}</dt><dd>{{ $order->assignedUser?->name ?? __('offen') }}</dd>
                <dt class="text-base-content/60">{{ __('Adresse') }}</dt>
                <dd>{{ $order->address_line }}, {{ $order->address_zip }} {{ $order->address_city }}</dd>
                <dt class="text-base-content/60">{{ __('Zeitfenster') }}</dt>
                <dd>{{ $order->time_window_start ?? '—' }} – {{ $order->time_window_end ?? '—' }}</dd>
                <dt class="text-base-content/60">{{ __('Service (min)') }}</dt><dd>{{ $order->service_minutes }}</dd>
                <dt class="text-base-content/60">{{ __('Tour') }}</dt>
                <dd>
                    @if ($order->tour)
                        <a href="{{ route('tours.show', $order->tour) }}" class="link">#{{ $order->tour->id }} ({{ $order->tour->tour_date?->format('d.m.Y') }})</a>
                    @else
                        —
                    @endif
                </dd>
            </dl>

            @if ($order->description)
                <h2 class="mt-4 text-sm font-medium">{{ __('Beschreibung') }}</h2>
                <p class="whitespace-pre-line text-sm">{{ $order->description }}</p>
            @endif
        </div>

        @if ($order->address_lat && $order->address_lng)
            <x-map :center="['lat' => (float) $order->address_lat, 'lng' => (float) $order->address_lng]"
                   :markers="[['lat' => (float) $order->address_lat, 'lng' => (float) $order->address_lng, 'label' => $order->title]]"
                   :zoom="14" height="320px" />
        @endif
    </div>
@endsection
