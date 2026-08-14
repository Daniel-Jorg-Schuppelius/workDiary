{{--
  Created on   : Sat Aug 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('print.orders.detail_title'))
@section('nav-title', __('print.orders.title'))

@section('content')
@php
    use App\Enums\Print\{PreflightStatus, PrintOrderStatus, PrintOutputKind};
    use App\Models\Print\PrintOrder;
    $status = $order->status;
    $mo = $order->manufacturingOrder;
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :title="__('print.orders.detail_title') . ' — ' . ($mo->number ?? '')">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-status-badge size="md" outline :tone="$status->tone()">{{ $status->label() }}</x-status-badge>
                <x-status-badge size="md" outline :tone="$order->preflight_status->tone()">{{ __('print.field.preflight') }}: {{ $order->preflight_status->label() }}</x-status-badge>
                <x-status-badge size="md" outline>{{ $order->output_kind->label() }}</x-status-badge>
                @if ($order->approved_at !== null && ! $order->approvalMatchesFile() && ! $status->isFinal())
                    <x-status-badge size="md" outline tone="error">{{ __('print.badge.approval_stale') }}</x-status-badge>
                @endif
            </div>
            <x-slot:actions>
                @if ($mo !== null)
                    <x-icon-btn icon="factory" size="sm" :href="route('manufacturing-orders.show', $mo)" show-label>{{ __('print.orders.action.manufacturing') }}</x-icon-btn>
                @endif
                <x-icon-btn icon="arrow_back" size="sm" :href="route('print-orders.index')" show-label>{{ __('Zur Liste') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    <x-validation-errors />

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('print.section.order')">
            <x-detail-grid class="grid-cols-2">
                <x-detail-grid.row :label="__('Nummer')">{{ $mo->number ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('print.field.article')">{{ $mo->article->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('print.field.quantity')">{{ $mo?->target_qty }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('print.field.due_at')">{{ optional($mo?->due_at)->fdatetime() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('Kunde')">{{ $mo->customer->name ?? __('print.field.walk_in') }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('print.field.good_total')">{{ $mo?->goodTotal() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('print.field.scrap_total')">{{ $mo?->scrapTotal() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('print.field.files_retain_until')">{{ optional($order->files_retain_until)->fdate() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('print.field.cancel_reason')">{{ $order->cancel_reason }}</x-detail-grid.row>
            </x-detail-grid>

            @can('update', $order)
                @if (! $status->isFinal())
                    <form method="POST" action="{{ route('print-orders.cancel', $order) }}" class="mt-3 flex flex-wrap items-end gap-2 text-sm">
                        @csrf
                        <input type="text" name="reason" placeholder="{{ __('print.field.reason') }} *" class="input input-sm input-bordered w-64" required aria-label="{{ __('print.field.reason') }}">
                        <button type="submit" class="btn btn-sm btn-outline btn-error">{{ __('print.orders.action.cancel') }}</button>
                    </form>
                @endif
            @endcan
        </x-card>

        <x-card :title="__('print.section.file')">
            <x-detail-grid class="grid-cols-2">
                <x-detail-grid.row :label="__('print.field.file')">
                    {{ $order->documentVersion?->original_name ?? '—' }}
                    @if ($order->files_purged_at !== null)
                        <x-status-badge size="sm" outline tone="neutral">{{ __('print.badge.file_purged') }}</x-status-badge>
                    @endif
                </x-detail-grid.row>
                <x-detail-grid.row :label="__('print.field.file_hash')"><span class="font-mono text-xs break-all">{{ $order->file_hash }}</span></x-detail-grid.row>
                <x-detail-grid.row :label="__('print.field.file_bound_at')">{{ optional($order->file_bound_at)->fdatetime() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('print.field.preflight_provider')">{{ $order->preflight_provider }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('print.field.preflight_at')">{{ optional($order->preflight_at)->fdatetime() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('print.field.override_reason')">{{ $order->preflight_override_reason }}</x-detail-grid.row>
            </x-detail-grid>

            @php
                $errors_ = collect(data_get($order->preflight_findings, 'errors', []));
                $warnings_ = collect(data_get($order->preflight_findings, 'warnings', []));
            @endphp
            @if ($errors_->isNotEmpty() || $warnings_->isNotEmpty())
                <div class="mt-3 space-y-1 text-sm">
                    @foreach ($errors_ as $finding)
                        <div class="alert alert-error py-1">{{ $finding }}</div>
                    @endforeach
                    @foreach ($warnings_ as $finding)
                        <div class="alert alert-warning py-1">{{ $finding }}</div>
                    @endforeach
                </div>
            @endif

            @can('update', $order)
                @if (! $status->isFinal())
                    <form method="POST" action="{{ route('print-orders.file', $order) }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-2 text-sm">
                        @csrf
                        <input type="file" name="file" class="file-input file-input-sm file-input-bordered" required aria-label="{{ __('print.field.file') }}">
                        <button type="submit" class="btn btn-sm">{{ __('print.orders.action.bind_file') }}</button>
                    </form>

                    @if ($order->hasProductionFile())
                        <div class="mt-3 flex flex-wrap items-start gap-2 text-sm">
                            <form method="POST" action="{{ route('print-orders.preflight.run', $order) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm">{{ __('print.orders.action.run_preflight') }}</button>
                            </form>
                            @if ($order->preflight_status === PreflightStatus::Failed)
                                <form method="POST" action="{{ route('print-orders.preflight.override', $order) }}" class="flex items-end gap-2">
                                    @csrf
                                    <input type="text" name="reason" placeholder="{{ __('print.field.override_reason') }} *" class="input input-sm input-bordered w-64" required aria-label="{{ __('print.field.override_reason') }}">
                                    <button type="submit" class="btn btn-sm btn-outline btn-warning">{{ __('print.orders.action.override') }}</button>
                                </form>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('print-orders.preflight.manual', $order) }}" class="mt-3 grid grid-cols-2 gap-2 text-sm">
                            @csrf
                            <textarea name="errors" rows="2" placeholder="{{ __('print.field.manual_errors') }}" class="textarea textarea-sm textarea-bordered" aria-label="{{ __('print.field.manual_errors') }}">{{ old('errors') }}</textarea>
                            <textarea name="warnings" rows="2" placeholder="{{ __('print.field.manual_warnings') }}" class="textarea textarea-sm textarea-bordered" aria-label="{{ __('print.field.manual_warnings') }}">{{ old('warnings') }}</textarea>
                            <button type="submit" class="btn btn-sm col-span-2">{{ __('print.orders.action.manual_preflight') }}</button>
                        </form>
                    @endif
                @endif
            @endcan
        </x-card>

        <x-card :title="__('print.section.approval')">
            @if ($order->production_snapshot !== null)
                <x-detail-grid class="grid-cols-2">
                    <x-detail-grid.row :label="__('print.snapshot.final_format')">{{ data_get($order->production_snapshot, 'final_format') }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('print.snapshot.pages')">{{ data_get($order->production_snapshot, 'pages') }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('print.snapshot.color_mode')">{{ data_get($order->production_snapshot, 'color_mode') }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('print.snapshot.color_profile')">{{ data_get($order->production_snapshot, 'color_profile') }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('print.snapshot.material')">{{ data_get($order->production_snapshot, 'material') }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('print.snapshot.grammage')">{{ data_get($order->production_snapshot, 'grammage') }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('print.snapshot.quantity')">{{ data_get($order->production_snapshot, 'quantity') }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('print.snapshot.due_date')">{{ data_get($order->production_snapshot, 'due_date') }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('print.snapshot.bleed_mm')">{{ data_get($order->production_snapshot, 'bleed_mm') }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('print.snapshot.finishing')">{{ collect(data_get($order->production_snapshot, 'finishing', []))->implode(', ') ?: null }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('print.field.approved_by')">{{ $order->approver->name ?? null }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('print.field.approved_at')">{{ optional($order->approved_at)->fdatetime() }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('print.field.approved_file_hash')"><span class="font-mono text-xs break-all">{{ $order->approved_file_hash }}</span></x-detail-grid.row>
                </x-detail-grid>
            @else
                <p class="text-sm opacity-70">{{ __('print.hint.no_snapshot') }}</p>
            @endif

            @can('update', $order)
                @if ($status === PrintOrderStatus::DataCheck)
                    <form method="POST" action="{{ route('print-orders.approve', $order) }}" class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        @csrf
                        <input type="text" name="final_format" value="{{ old('final_format') }}" placeholder="{{ __('print.snapshot.final_format') }} *" class="input input-sm input-bordered" required aria-label="{{ __('print.snapshot.final_format') }}">
                        <input type="number" name="pages" value="{{ old('pages') }}" min="1" placeholder="{{ __('print.snapshot.pages') }}" class="input input-sm input-bordered" aria-label="{{ __('print.snapshot.pages') }}">
                        <input type="text" name="color_mode" value="{{ old('color_mode', '4/4 CMYK') }}" placeholder="{{ __('print.snapshot.color_mode') }} *" class="input input-sm input-bordered" required aria-label="{{ __('print.snapshot.color_mode') }}">
                        <input type="text" name="color_profile" value="{{ old('color_profile') }}" placeholder="{{ __('print.snapshot.color_profile') }}" class="input input-sm input-bordered" aria-label="{{ __('print.snapshot.color_profile') }}">
                        <input type="text" name="material" value="{{ old('material') }}" placeholder="{{ __('print.snapshot.material') }} *" class="input input-sm input-bordered" required aria-label="{{ __('print.snapshot.material') }}">
                        <input type="text" name="grammage" value="{{ old('grammage') }}" placeholder="{{ __('print.snapshot.grammage') }}" class="input input-sm input-bordered" aria-label="{{ __('print.snapshot.grammage') }}">
                        <input type="number" name="quantity" value="{{ old('quantity', (string) $mo?->target_qty?->getNumericValue()) }}" min="1" placeholder="{{ __('print.snapshot.quantity') }} *" class="input input-sm input-bordered" required aria-label="{{ __('print.snapshot.quantity') }}">
                        <input type="date" name="due_date" value="{{ old('due_date', optional($mo?->due_at)->toDateString()) }}" class="input input-sm input-bordered" required aria-label="{{ __('print.snapshot.due_date') }}">
                        <input type="number" name="bleed_mm" value="{{ old('bleed_mm') }}" step="0.1" min="0" placeholder="{{ __('print.snapshot.bleed_mm') }}" class="input input-sm input-bordered" aria-label="{{ __('print.snapshot.bleed_mm') }}">
                        <input type="text" name="finishing" value="{{ old('finishing') }}" placeholder="{{ __('print.snapshot.finishing') }}" class="input input-sm input-bordered" aria-label="{{ __('print.snapshot.finishing') }}">
                        <button type="submit" class="btn btn-sm btn-primary col-span-2">{{ __('print.orders.action.approve') }}</button>
                    </form>
                @endif
            @endcan
        </x-card>

        <x-card :title="__('print.section.production')">
            <x-detail-grid class="grid-cols-2">
                <x-detail-grid.row :label="__('print.field.machine')">{{ $order->asset->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('print.field.production_started_at')">{{ optional($order->production_started_at)->fdatetime() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('print.field.qc_status')">{{ $order->qc_status !== null ? __('print.qc.' . $order->qc_status) : null }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('print.field.qc_by')">{{ $order->qcChecker->name ?? null }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('print.field.qc_note')">{{ $order->qc_note }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('print.field.issued_at')">{{ optional($order->issued_at)->fdatetime() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('print.field.handover_name')">{{ $order->handover_name }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('print.field.shipment')">{{ $order->shipment !== null ? ($order->shipment->carrier . ' ' . ($order->shipment->tracking_number ?? '')) : null }}</x-detail-grid.row>
            </x-detail-grid>

            @can('update', $order)
                @if ($status === PrintOrderStatus::Approved)
                    <form method="POST" action="{{ route('print-orders.production.start', $order) }}" class="mt-3 flex flex-wrap items-end gap-2 text-sm">
                        @csrf
                        <select name="asset_id" class="select select-sm select-bordered w-64" aria-label="{{ __('print.field.machine') }}">
                            <option value="">{{ __('print.field.without_machine') }}</option>
                            @foreach ($machines as $machine)
                                <option value="{{ $machine->sqid }}" @selected((string) old('asset_id') === $machine->sqid)>{{ $machine->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('print.orders.action.start_production') }}</button>
                    </form>
                @endif

                @if ($status === PrintOrderStatus::Rework)
                    <form method="POST" action="{{ route('print-orders.production.resume', $order) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn btn-sm">{{ __('print.orders.action.resume_production') }}</button>
                    </form>
                @endif

                @if (in_array($status, [PrintOrderStatus::InProduction, PrintOrderStatus::QualityCheck], true))
                    <form method="POST" action="{{ route('print-orders.quality-check', $order) }}" class="mt-3 flex flex-wrap items-end gap-2 text-sm">
                        @csrf
                        <select name="result" class="select select-sm select-bordered" required aria-label="{{ __('print.field.qc_status') }}">
                            <option value="{{ PrintOrder::QC_PASSED }}">{{ __('print.qc.passed') }}</option>
                            <option value="{{ PrintOrder::QC_REWORK }}">{{ __('print.qc.rework') }}</option>
                            <option value="{{ PrintOrder::QC_BLOCKED }}">{{ __('print.qc.blocked') }}</option>
                        </select>
                        <input type="text" name="note" placeholder="{{ __('print.field.qc_note') }}" class="input input-sm input-bordered w-64" aria-label="{{ __('print.field.qc_note') }}">
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('print.orders.action.quality_check') }}</button>
                    </form>
                @endif

                @if ($status === PrintOrderStatus::Ready)
                    <form method="POST" action="{{ route('print-orders.issue', $order) }}" class="mt-3 flex flex-wrap items-end gap-2 text-sm">
                        @csrf
                        @if ($order->output_kind === PrintOutputKind::Pickup)
                            <input type="text" name="handover_name" placeholder="{{ __('print.field.handover_name') }} *" class="input input-sm input-bordered w-56" required aria-label="{{ __('print.field.handover_name') }}">
                            <input type="text" name="handover_note" placeholder="{{ __('print.field.handover_note') }}" class="input input-sm input-bordered w-56" aria-label="{{ __('print.field.handover_note') }}">
                        @elseif ($order->output_kind === PrintOutputKind::Shipping)
                            <select name="shipment_id" class="select select-sm select-bordered w-64" required aria-label="{{ __('print.field.shipment') }}">
                                <option value="">…</option>
                                @foreach ($shipments as $shipment)
                                    <option value="{{ $shipment->sqid }}">{{ $shipment->carrier }} {{ $shipment->tracking_number ?? '#' . $shipment->sqid }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="text-sm opacity-70">{{ __('print.hint.counter_minimal') }}</span>
                        @endif
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('print.orders.action.issue') }}</button>
                    </form>
                @endif
            @endcan
        </x-card>

        @if ($claimLinks->isNotEmpty() || $canOpenClaim)
            <x-card :title="__('print.section.claims')">
                @if ($claimLinks->isEmpty())
                    <p class="text-sm opacity-70">{{ __('print.claim.none') }}</p>
                @else
                    <ul class="space-y-1 text-sm">
                        @foreach ($claimLinks as $link)
                            <li class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('claims.show', $link->claimCase) }}" class="link link-primary font-medium">{{ $link->claimCase?->number }}</a>
                                <span>{{ $link->claimCase?->title }}</span>
                                <x-status-badge outline>{{ $link->claimCase?->status?->label() }}</x-status-badge>
                                @if ($link->note)
                                    <span class="opacity-70">{{ $link->note }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($canOpenClaim)
                    <form method="POST" action="{{ route('print-orders.claim', $order) }}" class="mt-3 flex flex-wrap items-end gap-2 text-sm">
                        @csrf
                        <input type="text" name="description" placeholder="{{ __('print.claim.description') }}" class="input input-sm input-bordered w-72" aria-label="{{ __('print.claim.description') }}">
                        <input type="number" name="affected_quantity" step="0.0001" min="0.0001" placeholder="{{ __('print.claim.affected_quantity') }}" class="input input-sm input-bordered w-40" aria-label="{{ __('print.claim.affected_quantity') }}">
                        <button type="submit" class="btn btn-sm">{{ __('print.claim.open') }}</button>
                    </form>
                    <p class="mt-1 text-xs opacity-70">{{ __('print.hint.claim_reference') }}</p>
                @endif
            </x-card>
        @endif
    </div>
</x-page-shell>
@endsection
