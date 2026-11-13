{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kundenfrist (Feature 197, MVP-1632): Angaben, Verlauf und Schritte. Variablen: $deadline, $kindLabel, $ruleSetLabel, $canUpdate. --}}
@php
    $isOpen = $deadline->status->isOpen();
    $actions = $isOpen ? ['complete', 'extend', 'advance', 'waive'] : ['reopen'];
    $default = (string) old('action', $actions[0]);
@endphp

<x-modal
    :title="$kindLabel . ' · ' . $deadline->period_key->label()"
    :eyebrow="$deadline->customer?->name ?? __('customer_deadline.title')"
    icon="event_upcoming"
    tone="primary"
    :badge="$deadline->status->label()"
    :badge-tone="$deadline->status->tone()"
    :action="$canUpdate ? route('customer-deadlines.update', $deadline) : null"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('customer_deadline.dialog.submit')">

    @if ($canUpdate)
        <input type="hidden" name="_dialog_url" value="{{ route('customer-deadlines.edit', $deadline) . '?dialog=1' }}">
    @endif

    <x-form-group :legend="__('customer_deadline.dialog.facts')" icon="info" tone="ghost" cols="2">
        <x-detail-grid class="sm:col-span-2">
            <x-detail-grid.row :label="__('customer_deadline.column.rule_set')">{{ $ruleSetLabel }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('customer_deadline.column.due_on')">{{ $deadline->due_on->fdate() }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('customer_deadline.column.original_due_on')">{{ $deadline->original_due_on->fdate() }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('customer_deadline.column.responsible')">{{ $deadline->responsible?->name ?? '—' }}</x-detail-grid.row>
            @if ($deadline->advance_requested_on !== null)
                <x-detail-grid.row :label="__('customer_deadline.column.advance_requested_on')">{{ $deadline->advance_requested_on->fdate() }}</x-detail-grid.row>
            @endif
            @if ($deadline->extension_reason)
                <x-detail-grid.row :label="__('customer_deadline.column.extension_reason')">{{ $deadline->extension_reason }}</x-detail-grid.row>
            @endif
            @if ($deadline->completed_at !== null)
                <x-detail-grid.row :label="__('customer_deadline.column.completed_at')">{{ $deadline->completed_at->fdate() }}@if ($deadline->completer) · {{ $deadline->completer->name }}@endif</x-detail-grid.row>
            @endif
            @if ($deadline->diaryEntry !== null)
                <x-detail-grid.row :label="__('customer_deadline.column.order')"><x-order-link :entry="$deadline->diaryEntry" link-class="link link-hover">{{ $deadline->diaryEntry->title ?: '#' . $deadline->diaryEntry->id }}</x-order-link></x-detail-grid.row>
            @endif
        </x-detail-grid>
        @if ($deadline->is_region_assumed)
            <p class="text-xs text-warning sm:col-span-2"><x-icon name="location_off" class="align-middle" /> {{ __('customer_deadline.region_assumed') }}</p>
        @endif
    </x-form-group>

    @if ($canUpdate)
        <x-form-group :legend="__('customer_deadline.dialog.step')" icon="task_alt" tone="primary" cols="2">
            <div class="space-y-3 sm:col-span-2" x-data="reveal(@js($default))">
                <fieldset class="flex flex-wrap gap-4">
                    <legend class="sr-only">{{ __('customer_deadline.dialog.step') }}</legend>
                    @foreach ($actions as $action)
                        <label class="flex items-center gap-2">
                            <input type="radio" name="action" value="{{ $action }}" class="radio radio-sm" x-model="value" @checked($default === $action)>
                            {{ __('customer_deadline.action.' . $action) }}
                        </label>
                    @endforeach
                </fieldset>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div x-show="is('advance')" x-cloak>
                        <x-input-field type="date" id="cd-requested-on" name="requested_on" :label="__('customer_deadline.field.requested_on')" :value="old('requested_on')" :hint="__('customer_deadline.hint.advance')" />
                    </div>
                    <div x-show="is('extend') || is('advance')" x-cloak>
                        <x-input-field type="date" id="cd-due-on" name="due_on" :label="__('customer_deadline.field.new_due_on')" :value="old('due_on')" />
                    </div>
                </div>
                <x-textarea-field id="cd-text" name="text" :label="__('customer_deadline.field.text')" rows="2" :value="old('text')" :hint="__('customer_deadline.hint.text')" />
            </div>
        </x-form-group>
    @endif

    <x-form-group :legend="__('customer_deadline.dialog.history')" icon="history" tone="ghost">
        <x-journal :entries="$deadline->journal" :payload-keys="['text', 'reason', 'from', 'to']" newest-first />
    </x-form-group>

    <x-validation-errors />
</x-modal>
