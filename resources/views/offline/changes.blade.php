@extends('layouts.app')

@section('title', __('offline.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('offline.title'))

@section('content')
<x-index-page :subtitle="__('offline.subtitle')">
    {{-- Inhalte kommen aus der IndexedDB des Geräts (Outbox + abgelehnte
         Befehle); gerendert von resources/js/offline-sync.js. Alle Texte
         liegen hier serverseitig (CSP-konform, übersetzt ×5). --}}
    <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
        <x-icon name="cloud_off" />
        <span>{{ __('offline.notice') }}</span>
    </div>

    <div data-offline-changes
         data-label-pending="{{ __('offline.section.pending') }}"
         data-label-rejected="{{ __('offline.section.rejected') }}"
         data-label-type-attendance-clock-in="{{ __('offline.type.clock_in') }}"
         data-label-type-attendance-clock-out="{{ __('offline.type.clock_out') }}"
         data-label-type-comment-diary="{{ __('offline.type.comment') }}"
         data-label-type-form-submission="{{ __('offline.type.form') }}"
         class="space-y-6">
        <p data-offline-empty class="text-sm text-base-content/60" hidden>{{ __('offline.empty') }}</p>
        <section data-offline-section="outbox" class="space-y-2" hidden>
            <h2 class="text-base font-semibold" data-section-heading></h2>
            <ul class="space-y-2" data-section-list></ul>
        </section>
        <section data-offline-section="rejected" class="space-y-2" hidden>
            <h2 class="text-base font-semibold" data-section-heading></h2>
            <ul class="space-y-2" data-section-list></ul>
        </section>
    </div>

    <template data-sync-item-template>
        <li class="flex flex-wrap items-center gap-3 rounded-box border border-base-300 bg-base-100 p-3 shadow-xs">
            <div class="min-w-0 flex-1">
                <p class="font-medium" data-item-type></p>
                <p class="text-xs text-base-content/60 tabular-nums" data-item-time></p>
                <p class="text-sm text-error" data-item-errors hidden></p>
            </div>
            <div class="flex items-center gap-1.5">
                <button type="button" class="btn btn-xs btn-primary" data-item-retry hidden>{{ __('offline.action.retry') }}</button>
                <button type="button" class="btn btn-xs btn-ghost text-error" data-item-discard>{{ __('offline.action.discard') }}</button>
            </div>
        </li>
    </template>
</x-index-page>
@endsection
