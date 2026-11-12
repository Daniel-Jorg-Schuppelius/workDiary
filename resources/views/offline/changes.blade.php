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

@php
    // Anzeigenamen je Befehlstyp und je Feld (Server-Stand, Bearbeiten) — alle Offline-Handler (MVP-1595).
    $typeLabels = [
        'attendance.clock-in' => __('offline.type.clock_in'),
        'attendance.clock-out' => __('offline.type.clock_out'),
        'attendance.correct' => __('offline.type.attendance_correct'),
        'comment.diary' => __('offline.type.comment'),
        'form.submission' => __('offline.type.form'),
        'time.entry' => __('offline.type.time_entry'),
        'material.usage' => __('offline.type.material_usage'),
        'learning.unit-complete' => __('offline.type.learning_unit'),
        'inventory.count' => __('offline.type.inventory_count'),
        'tour.stop-arrive' => __('offline.type.tour_arrive'),
        'tour.stop-depart' => __('offline.type.tour_depart'),
        'patrol.scan' => __('offline.type.patrol_scan'),
        'takeoff.line' => __('offline.type.takeoff_line'),
    ];
    // Bearbeitbare Felder beim erneuten Senden; Verweise (Sqids) bleiben, wie sie sind.
    $editFields = [
        'attendance.correct' => ['started_at', 'ended_at', 'break_minutes', 'reason'],
        'time.entry' => ['date', 'minutes', 'start_time', 'end_time', 'break_minutes', 'description'],
        'material.usage' => ['date', 'description', 'quantity', 'unit'],
    ];
    $fieldLabels = collect(['started_at', 'ended_at', 'break_minutes', 'reason', 'date', 'minutes', 'start_time', 'end_time', 'description', 'quantity', 'unit'])
        ->mapWithKeys(fn (string $key): array => [$key => __('validation.attributes.' . $key)])
        ->put('missing', __('offline.field.missing'))
        ->put('lock', __('offline.field.lock'))
        ->all();
@endphp

@section('content')
<x-index-page :subtitle="__('offline.subtitle')">
    {{-- Inhalte kommen aus der IndexedDB des Geräts (Outbox, Konflikte,
         abgelehnte Befehle) und für offene Konflikte zusätzlich vom Server;
         gerendert von resources/js/offline-sync.js. Alle Texte liegen hier
         serverseitig (CSP-konform, übersetzt ×5). --}}
    <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
        <x-icon name="cloud_off" />
        <span>{{ __('offline.notice') }}</span>
    </div>

    <div data-offline-changes
         data-resolve-url="{{ route('api.internal.sync.conflicts.resolve') }}"
         data-server-conflicts="{{ json_encode($serverConflicts ?? []) }}"
         data-type-labels="{{ json_encode($typeLabels) }}"
         data-field-labels="{{ json_encode($fieldLabels) }}"
         data-edit-fields="{{ json_encode($editFields) }}"
         data-label-pending="{{ __('offline.section.pending') }}"
         data-label-rejected="{{ __('offline.section.rejected') }}"
         data-label-conflict="{{ __('offline.section.conflict') }}"
         data-label-conflict-hint="{{ __('offline.conflict_hint') }}"
         data-label-take-server="{{ __('offline.action.take_server') }}"
         data-label-remote-hint="{{ __('offline.remote_hint') }}"
         data-label-resolve-failed="{{ __('offline.resolve_failed') }}"
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
                <p class="text-sm text-muted" data-item-remote hidden></p>
            </div>
            <div class="flex items-center gap-1.5">
                <x-button size="xs" data-item-retry hidden>{{ __('offline.action.retry') }}</x-button>
                <x-button tone="ghost" size="xs" data-item-edit hidden>{{ __('offline.action.edit') }}</x-button>
                <x-button tone="warning" size="xs" data-item-force hidden>{{ __('offline.action.force_local') }}</x-button>
                <x-button tone="ghost" size="xs" class="text-error" data-item-discard>{{ __('offline.action.discard') }}</x-button>
            </div>
            {{-- Bearbeiten und erneut senden: Felder baut das Skript aus data-edit-fields. --}}
            <form class="w-full space-y-2" data-item-editor hidden>
                <div class="grid gap-2 sm:grid-cols-2" data-editor-fields></div>
                <div class="flex justify-end gap-1.5">
                    <x-button tone="ghost" size="xs" data-editor-cancel>{{ __('offline.action.cancel_edit') }}</x-button>
                    <x-button type="submit" tone="primary" size="xs">{{ __('offline.action.send_edited') }}</x-button>
                </div>
            </form>
        </x-card>
    </template>
</x-index-page>
@endsection
