{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _cell_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Zelle des Leistungsplans (Feature 199, MVP-1638). Variablen: $customer, $service, $serviceLabel, $period, $cell, $order, $deadline, $waiver, $responsible, $canManage, $canAnnotate, $canCreateOrder, $params. --}}
@php
    $actions = [];
    if ($canAnnotate) {
        $actions[] = 'note';
    }
    if ($canManage && $order === null) {
        $actions[] = $waiver === null ? 'waive' : 'unwaive';
    }
    $default = (string) old('action', $actions[0] ?? 'note');
@endphp

<x-modal
    :title="$serviceLabel . ' · ' . $period->label()"
    :eyebrow="$customer->name"
    icon="calendar_view_month"
    tone="primary"
    :badge="$cell->state->label()"
    :badge-tone="$cell->state->tone()"
    :action="$actions !== [] ? route('service-plan.cell.update', $params) : null"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('service_plan.dialog.submit')">

    @if ($actions !== [])
        <input type="hidden" name="_dialog_url" value="{{ route('service-plan.cell.edit', $params + ['dialog' => 1]) }}">
    @endif

    <x-slot:footerExtra>
        @if ($order !== null)
            <x-order-link :entry="$order" link-class="btn btn-sm btn-outline">
                <x-icon name="open_in_new" /> {{ __('service_plan.dialog.open_order') }}
            </x-order-link>
        @elseif ($canCreateOrder)
            <x-icon-btn icon="add_task" tone="outline" size="sm" data-entry-modal-trigger
                        :href="route('service-plan.cell.order', $params + ['dialog' => 1])"
                        show-label>{{ __('service_plan.dialog.create_order') }}</x-icon-btn>
        @endif
    </x-slot:footerExtra>

    <x-form-group :legend="__('service_plan.dialog.facts')" icon="info" tone="ghost" cols="2">
        <x-detail-grid class="sm:col-span-2">
            <x-detail-grid.row :label="__('service_plan.column.state')">
                {{ $cell->state->label() }}@if ($cell->overdue) · <span class="font-semibold text-error">{{ __('service_plan.overdue') }}</span>@endif
            </x-detail-grid.row>
            <x-detail-grid.row :label="__('service_plan.column.mandatory')">{{ $cell->mandatory ? __('service_plan.mandatory') : __('service_plan.optional') }}</x-detail-grid.row>
            @if ($order !== null)
                <x-detail-grid.row :label="__('service_plan.column.order')">
                    <x-order-link :entry="$order" link-class="link link-hover">{{ $order->title ?: '#' . $order->id }}</x-order-link>
                    <span class="text-xs text-muted">· {{ $order->status->label() }}</span>
                </x-detail-grid.row>
            @endif
            <x-detail-grid.row :label="__('service_plan.column.responsible')">{{ $responsible?->name ?? '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('service_plan.column.due')">{{ $cell->due?->fdate() ?? '—' }}</x-detail-grid.row>
            @if ($deadline !== null)
                <x-detail-grid.row :label="__('service_plan.column.deadline')">
                    @can('view', $deadline)
                        <a class="link link-hover" data-entry-modal-trigger href="{{ route('customer-deadlines.edit', $deadline) }}?dialog=1">{{ $deadline->due_on->fdate() }}</a>
                    @else
                        {{ $deadline->due_on->fdate() }}
                    @endcan
                </x-detail-grid.row>
                @if ($deadline->advance_requested_on !== null)
                    <x-detail-grid.row :label="__('service_plan.column.advance_requested_on')">{{ $deadline->advance_requested_on->fdate() }}</x-detail-grid.row>
                @endif
            @endif
            @if ($cell->documentsReceivedOn !== null)
                <x-detail-grid.row :label="__('service_plan.column.documents')">{{ $cell->documentsReceivedOn->fdate() }}</x-detail-grid.row>
            @endif
            @if ($cell->note !== null && $cell->note !== '')
                <x-detail-grid.row :label="__('service_plan.column.note')">{{ $cell->note }}</x-detail-grid.row>
            @endif
            @if ($waiver !== null)
                <x-detail-grid.row :label="__('service_plan.column.waiver')">
                    {{ $waiver->reason }}
                    <div class="text-xs text-muted">{{ __('service_plan.waived_by', ['name' => $waiver->creator?->name ?? '—', 'date' => $waiver->created_at?->fdate() ?? '—']) }}</div>
                </x-detail-grid.row>
            @endif
        </x-detail-grid>
        @if ($order === null && ! $canCreateOrder && $waiver === null)
            <div class="sm:col-span-2"><x-empty-state compact icon="assignment" :title="__('service_plan.dialog.no_order_hint')" /></div>
        @endif
    </x-form-group>

    @if ($actions !== [])
        <x-form-group :legend="__('service_plan.dialog.step')" icon="edit_note" tone="primary" cols="2">
            <div class="space-y-3 sm:col-span-2" x-data="reveal(@js($default))">
                <fieldset class="flex flex-wrap gap-4">
                    <legend class="sr-only">{{ __('service_plan.dialog.step') }}</legend>
                    @foreach ($actions as $action)
                        <label class="flex items-center gap-2">
                            <input type="radio" name="action" value="{{ $action }}" class="radio radio-sm" x-model="value" @checked($default === $action)>
                            {{ __('service_plan.action.' . $action) }}
                        </label>
                    @endforeach
                </fieldset>
                @if (in_array('note', $actions, true))
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2" x-show="is('note')" x-cloak>
                        <x-input-field type="date" id="sp-documents" name="documents_received_on" :label="__('service_plan.field.documents_received_on')"
                                       :value="old('documents_received_on', $cell->documentsReceivedOn?->toDateString())" />
                        <x-textarea-field id="sp-note" name="note" span="2" :label="__('service_plan.field.note')" rows="2" maxlength="2000" :value="old('note', $cell->note)" />
                    </div>
                @endif
                @if (in_array('waive', $actions, true))
                    <div x-show="is('waive')" x-cloak>
                        <x-textarea-field id="sp-reason" name="reason" :label="__('service_plan.field.reason')" rows="2" maxlength="1000" :value="old('reason')" :hint="__('service_plan.hint.waive')" />
                    </div>
                @endif
                @if (in_array('unwaive', $actions, true))
                    <p class="text-sm text-muted" x-show="is('unwaive')" x-cloak>{{ __('service_plan.hint.unwaive') }}</p>
                @endif
            </div>
        </x-form-group>
    @endif

    <x-validation-errors />
</x-modal>
