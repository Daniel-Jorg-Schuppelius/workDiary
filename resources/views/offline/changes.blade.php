{{--
  Created on   : Wed Jul 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : changes.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
         data-label-conflict="{{ __('offline.section.conflict') }}"
         data-label-conflict-hint="{{ __('offline.conflict_hint') }}"
         data-label-take-server="{{ __('offline.action.take_server') }}"
         data-label-type-attendance-clock-in="{{ __('offline.type.clock_in') }}"
         data-label-type-attendance-clock-out="{{ __('offline.type.clock_out') }}"
         data-label-type-comment-diary="{{ __('offline.type.comment') }}"
         data-label-type-form-submission="{{ __('offline.type.form') }}"
         data-label-type-attendance-correct="{{ __('offline.type.attendance_correct') }}"
         class="space-y-6">
        <x-empty-state icon="cloud_done" :title="__('offline.empty')" compact data-offline-empty hidden />
        <section data-offline-section="outbox" class="space-y-2" hidden>
            <h2 class="text-base font-semibold" data-section-heading></h2>
            <ul class="space-y-2" data-section-list></ul>
        </section>
        {{-- Konflikte zuerst: nur sie brauchen eine Entscheidung. --}}
        <section data-offline-section="conflicts" class="space-y-2" hidden>
            <h2 class="text-base font-semibold" data-section-heading></h2>
            <ul class="space-y-2" data-section-list></ul>
        </section>
        <section data-offline-section="rejected" class="space-y-2" hidden>
            <h2 class="text-base font-semibold" data-section-heading></h2>
            <ul class="space-y-2" data-section-list></ul>
        </section>
    </div>

    <template data-sync-item-template>
        <x-card as="li" padding="p-3" class="flex flex-wrap items-center gap-3">
            <div class="min-w-0 flex-1">
                <p class="font-medium" data-item-type></p>
                <p class="text-xs text-muted tabular-nums" data-item-time></p>
                <p class="text-sm text-error" data-item-errors hidden></p>
                <p class="text-sm text-warning" data-item-server hidden></p>
            </div>
            <div class="flex items-center gap-1.5">
                <x-button size="xs" data-item-retry hidden>{{ __('offline.action.retry') }}</x-button>
                <x-button tone="warning" size="xs" data-item-force hidden>{{ __('offline.action.force_local') }}</x-button>
                <x-button tone="ghost" size="xs" class="text-error" data-item-discard>{{ __('offline.action.discard') }}</x-button>
            </div>
        </x-card>
    </template>
</x-index-page>
@endsection
