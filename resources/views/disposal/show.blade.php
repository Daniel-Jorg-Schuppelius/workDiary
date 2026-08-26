{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', $job->number)
@section('nav-title', __('disposal.show.nav'))

@section('content')
@php
    $isEditable = $job->status->isEditable();
    $canUpdate = auth()->user()?->can('update', $job) ?? false;
    $canHandover = in_array($job->status, [
        \App\Enums\Disposal\DisposalJobStatus::Collected,
        \App\Enums\Disposal\DisposalJobStatus::InTreatment,
        \App\Enums\Disposal\DisposalJobStatus::HandedOver,
    ], true);
@endphp
<x-page-shell>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    <x-validation-errors />

    <x-slot:toolbar>
        <x-page-toolbar :title="__('disposal.show.title', ['number' => $job->number]) . ' — ' . ($job->customer->name ?? '')">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-status-badge size="md" :tone="$job->status->tone()" outline>{{ $job->status->label() }}</x-status-badge>
                @if ($job->isSigned())
                    <span class="badge badge-outline badge-success">{{ __('disposal.badge.signed') }}</span>
                @endif
            </div>
            <x-slot:actions>
                @if ($canUpdate)
                    @if ($job->status === \App\Enums\Disposal\DisposalJobStatus::Draft)
                        <form method="POST" action="{{ route('disposal.collect', $job) }}">@csrf
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('disposal.action.collect') }}</button>
                        </form>
                    @endif
                    @if ($job->status === \App\Enums\Disposal\DisposalJobStatus::Collected)
                        <form method="POST" action="{{ route('disposal.treatment', $job) }}">@csrf
                            <button type="submit" class="btn btn-sm">{{ __('disposal.action.start_treatment') }}</button>
                        </form>
                    @endif
                    @if (in_array($job->status, [\App\Enums\Disposal\DisposalJobStatus::Collected, \App\Enums\Disposal\DisposalJobStatus::InTreatment], true))
                        <form method="POST" action="{{ route('disposal.handed-over', $job) }}">@csrf
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('disposal.action.hand_over') }}</button>
                        </form>
                    @endif
                @endif
                @can('complete', $job)
                    @if ($job->status === \App\Enums\Disposal\DisposalJobStatus::HandedOver)
                        <form method="POST" action="{{ route('disposal.complete', $job) }}"
                              data-confirm-dialog data-confirm-message="{{ __('disposal.confirm.complete') }}">@csrf
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('Abschließen') }}</button>
                        </form>
                    @endif
                    @if ($job->status->isOpen())
                        <button type="button" class="btn btn-sm btn-ghost text-error"
                                data-open-dialog="disposal-cancel-dialog">{{ __('Stornieren') }}</button>
                    @endif
                @endcan
                @if ($canUpdate && $job->status->isEditable())
                    <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger
                                :href="route('disposal.edit', $job)" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                @endif
                <x-icon-btn icon="picture_as_pdf" size="sm" :href="route('disposal.pdf', $job)"
                            target="_blank" show-label>{{ __('disposal.action.pdf_preview') }}</x-icon-btn>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('disposal.index')" show-label>{{ __('Zur Liste') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    {{-- a) Aktenkopf --}}
    <x-card :title="__('disposal.show.section.job')">
        <x-detail-grid class="grid-cols-2">
            <x-detail-grid.row :label="__('Kunde')">{{ $job->customer->name ?? '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('disposal.field.site')">{{ $job->site?->name ?? '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('disposal.field.diary_entry')">
                @if ($job->diaryEntry !== null)
                    <a class="link" href="{{ route('diary.show', $job->diaryEntry) }}">#{{ $job->diaryEntry->id }} {{ $job->diaryEntry->title }}</a>
                @else
                    —
                @endif
            </x-detail-grid.row>
            <x-detail-grid.row :label="__('Verantwortlich')">{{ $job->responsible->name ?? '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('disposal.field.picked_up_on')">{{ $job->picked_up_on?->fdate() ?? '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('disposal.field.total_weight')">{{ $job->total_weight_kg !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $job->total_weight_kg, 3, withThousandsSeparator: true) . ' kg' : '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('disposal.field.created')">{{ $job->creator->name ?? '—' }} · {{ $job->created_at?->fdatetime() }}</x-detail-grid.row>
            @if ($job->status === \App\Enums\Disposal\DisposalJobStatus::Cancelled)
                <x-detail-grid.row :label="__('disposal.field.cancelled_at')" class="text-error">{{ $job->cancelled_at?->fdatetime() ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('disposal.field.cancel_reason')">{{ $job->cancel_reason ?? '—' }}</x-detail-grid.row>
            @endif
            @if ($job->status === \App\Enums\Disposal\DisposalJobStatus::Completed)
                <x-detail-grid.row :label="__('disposal.field.completed_at')">{{ $job->completed_at?->fdatetime() ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('disposal.field.completed_by')">{{ $users->firstWhere('id', $job->completed_by)->name ?? '—' }}</x-detail-grid.row>
            @endif
        </x-detail-grid>
        @if ($job->notes !== null)
            <p class="mt-2 whitespace-pre-line text-sm">{{ $job->notes }}</p>
        @endif
    </x-card>

    {{-- b) Abschluss-Prüfung (offene Gates) --}}
    @if ($completionBlockers !== [] && $job->status->isOpen())
        <x-card :title="__('disposal.show.section.blockers')" icon="warning" class="border-warning">
            <ul class="list-disc pl-5 text-sm">
                @foreach ($completionBlockers as $blocker)
                    <li>{{ $blocker }}</li>
                @endforeach
            </ul>
        </x-card>
    @endif

    {{-- c) Geräteliste --}}
    <x-card :title="__('disposal.show.section.items')" padding="p-0">
        <x-slot:actions>
            @if ($isEditable && $canUpdate)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-open-dialog="disposal-item-create"
                            show-label>{{ __('disposal.action.add_item') }}</x-icon-btn>
            @endif
        </x-slot:actions>
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Kategorie') }}</th>
                    <th>{{ __('disposal.item.col.device') }}</th>
                    <th>{{ __('Seriennummer') }}</th>
                    <th class="text-right">{{ __('Menge') }}</th>
                    <th class="text-right">{{ __('disposal.item.col.weight') }}</th>
                    <th>{{ __('disposal.item.col.avv') }}</th>
                    <th>{{ __('disposal.item.col.data_storage') }}</th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($job->items as $item)
                <tr>
                    <td>{{ $item->category }}</td>
                    <td>{{ trim(($item->manufacturer ?? '') . ' ' . ($item->model ?? '')) ?: '—' }}</td>
                    <td class="font-mono">{{ $item->serial_number ?? '—' }}</td>
                    <td class="text-right font-mono">{{ $item->quantity }}</td>
                    <td class="text-right font-mono">{{ $item->weight_kg !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $item->weight_kg, 3, withThousandsSeparator: true) : '—' }}</td>
                    <td>
                        <span class="font-mono">{{ $item->avv_code }}</span>
                        @if ($item->is_hazardous)
                            <x-status-badge size="xs" tone="error">{{ __('disposal.badge.hazardous') }}</x-status-badge>
                        @endif
                    </td>
                    <td>
                        @if ($item->has_data_storage)
                            @if ($item->treatments->isNotEmpty())
                                {{ trans_choice('disposal.item.treatments_count', $item->treatments->count(), ['count' => $item->treatments->count()]) }}
                            @else
                                <x-status-badge size="xs" tone="warning">{{ __('disposal.item.treatment_missing') }}</x-status-badge>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($isEditable && $canUpdate)
                            <x-icon-btn icon="edit" :label="__('Bearbeiten')" data-open-dialog="disposal-item-edit-{{ $item->id }}" />
                            <x-icon-btn icon="hard_drive" :label="__('disposal.action.add_treatment')" data-open-dialog="disposal-treatment-{{ $item->id }}" />
                            <form method="POST" action="{{ route('disposal.items.destroy', $item) }}" class="inline"
                                  data-confirm-dialog data-confirm-message="{{ __('disposal.confirm.delete_item') }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </form>
                        @endif
                    </td>
                </tr>
                @if ($item->treatments->isNotEmpty())
                    <tr>
                        <td colspan="8" class="bg-base-200/40">
                            <ul class="space-y-1 py-1 text-xs text-base-content/80">
                                @foreach ($item->treatments as $treatment)
                                    <li class="flex flex-wrap items-center gap-x-2">
                                        <x-icon name="hard_drive" size="1em" class="text-muted" />
                                        <span>{{ $treatment->media_type->label() }}</span>
                                        <span>· {{ $treatment->method->label() }}</span>
                                        <span class="font-mono">· DIN 66399 {{ $treatment->dinLevel() }}</span>
                                        @if ($treatment->protection_class !== null)
                                            <span>· {{ __('disposal.treatment.protection_class_short', ['class' => $treatment->protection_class]) }}</span>
                                        @endif
                                        <span>· {{ $treatment->treated_at->fdatetime() }}</span>
                                        <span>· {{ $treatment->performer->name ?? '—' }}</span>
                                        @if ($treatment->evidence_reference !== null)
                                            <span class="font-mono">· {{ $treatment->evidence_reference }}</span>
                                        @endif
                                        @if ($isEditable && $canUpdate)
                                            <form method="POST" action="{{ route('disposal.treatments.destroy', $treatment) }}" class="inline"
                                                  data-confirm-dialog data-confirm-message="{{ __('disposal.confirm.delete_treatment') }}">
                                                @csrf
                                                @method('DELETE')
                                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                            </form>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                @endif
            @empty
                <x-table.empty icon="recycling" :colspan="8" :title="__('disposal.item.empty')" compact />
            @endforelse
        </x-table>
    </x-card>

    {{-- d) Entsorger-Übergaben --}}
    <x-card :title="__('disposal.show.section.handovers')" padding="p-0">
        <x-slot:actions>
            @if ($canHandover && $canUpdate)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-open-dialog="disposal-handover-create"
                            show-label>{{ __('disposal.action.add_handover') }}</x-icon-btn>
            @endif
        </x-slot:actions>
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('disposal.handover.col.disposer') }}</th>
                    <th>{{ __('disposal.handover.col.proof_type') }}</th>
                    <th>{{ __('disposal.handover.col.document_number') }}</th>
                    <th>{{ __('Datum') }}</th>
                    <th>{{ __('disposal.handover.col.certificate') }}</th>
                    <th>{{ __('disposal.handover.col.document') }}</th>
                    <th class="text-right">{{ __('Aktionen') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($job->handovers as $handover)
                <tr>
                    <td>{{ $handover->disposer->name ?? '—' }}</td>
                    <td>{{ $handover->proof_type->label() }}</td>
                    <td class="font-mono">{{ $handover->document_number }}</td>
                    <td>{{ $handover->handed_over_on->fdate() }}</td>
                    <td class="font-mono">{{ $handover->certificate_reference ?? '—' }}</td>
                    <td>
                        @if ($handover->document !== null)
                            <a class="link" href="{{ route('documents.show', $handover->document) }}">{{ $handover->document->title }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right">
                        @if ($job->status->isOpen() && $canUpdate)
                            <form method="POST" action="{{ route('disposal.handovers.destroy', $handover) }}" class="inline"
                                  data-confirm-dialog data-confirm-message="{{ __('disposal.confirm.delete_handover') }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty icon="handshake" :colspan="7" :title="__('disposal.handover.empty')" compact />
            @endforelse
        </x-table>
    </x-card>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- e) Übernahme-Bestätigung (Kunden-Unterschrift) --}}
        <x-card :title="__('disposal.show.section.signature')">
            @if ($job->isSigned())
                <div class="flex flex-wrap items-start gap-4">
                    <div class="text-sm">
                        <div><strong>{{ $job->signer_name }}</strong></div>
                        <div class="mt-1 text-muted">{{ __('disposal.sign.signed_at') }}: {{ $job->signed_at?->fdatetime() }}</div>
                        @if ($job->signature_hash !== null)
                            <div class="text-xs break-all text-muted">{{ __('disposal.sign.hash') }}: {{ \Illuminate\Support\Str::limit($job->signature_hash, 24) }}</div>
                        @endif
                    </div>
                    @if ($job->signatureAttachment !== null)
                        <div>
                            <img src="{{ \App\Http\Controllers\AttachmentController::downloadUrl($job->signatureAttachment) }}"
                                 alt="{{ __('Unterschrift') }}" class="max-h-32 rounded border border-base-300 bg-white p-2">
                        </div>
                    @endif
                </div>
            @elseif ($job->status->isOpen() && $canUpdate)
                {{-- Signature-Pad (Muster timesheets/_signature_pad): Alpine-Komponente
                     signaturePad, signature_pad-Klasse via Lazy-Entry signature.js. --}}
                @once
                    @push('scripts')
                        @vite('resources/js/signature.js')
                    @endpush
                @endonce
                <div x-data="signaturePad" class="flex w-full max-w-md flex-col gap-3">
                    <input type="text" x-model="customerName" placeholder="{{ __('disposal.sign.signer_name') }}"
                           aria-label="{{ __('disposal.sign.signer_name') }}"
                           class="input input-bordered input-sm w-full" required>

                    <div class="rounded-box border border-base-300 bg-white p-2">
                        <canvas x-ref="canvas" class="block h-32 w-full touch-none rounded bg-white"></canvas>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <button type="button" class="btn btn-ghost btn-xs" @click="clear()">{{ __('Leeren') }}</button>
                        <span class="text-xs text-muted" x-show="hasSignature">{{ __('disposal.sign.hint') }}</span>
                    </div>

                    <form method="POST" action="{{ route('disposal.sign', $job) }}" @submit="prepare($event)" class="flex">
                        @csrf
                        <input type="hidden" name="signature" x-ref="sigInput">
                        <input type="hidden" name="signer_name" :value="customerName">
                        <button class="btn btn-primary btn-sm w-full" :disabled="submitDisabled">{{ __('disposal.action.sign') }}</button>
                    </form>
                </div>
            @else
                <p class="text-sm text-muted">{{ __('disposal.sign.missing') }}</p>
            @endif
        </x-card>

        {{-- f) Kundennachweis (DMS-Dokument) --}}
        <x-card :title="__('disposal.show.section.record')">
            @if ($job->recordDocument !== null)
                <div class="text-sm">
                    <a class="link font-medium" href="{{ route('documents.show', $job->recordDocument) }}">{{ $job->recordDocument->title }}</a>
                    <span class="text-muted">· v{{ $job->recordDocument->currentVersion?->version_no ?? '—' }}</span>
                </div>
                <p class="mt-2 text-xs text-muted">{{ __('disposal.record.released_hint') }}</p>
            @else
                <p class="text-sm text-muted">{{ __('disposal.record.pending_hint') }}</p>
            @endif
        </x-card>
    </div>

    {{-- g) Verlauf (append-only Nachweiskette) --}}
    <x-card :title="__('Verlauf')" padding="p-0">
        <ul class="divide-y divide-base-200">
            @forelse ($job->events as $event)
                <li class="flex flex-wrap items-baseline gap-x-2 px-4 py-2 text-sm">
                    <span class="font-mono text-xs text-muted">{{ $event->created_at->fdatetime() }}</span>
                    <span class="font-medium">{{ $event->event->label() }}</span>
                    <span class="text-muted">{{ $event->actor->name ?? '—' }}</span>
                    @php($eventNote = $event->payload['note'] ?? $event->payload['reason'] ?? null)
                    @if ($eventNote !== null)
                        <span class="w-full text-xs text-muted sm:w-auto">„{{ $eventNote }}"</span>
                    @endif
                </li>
            @empty
                <li class="px-4 py-3 text-sm text-muted">{{ __('Keine Einträge vorhanden') }}</li>
            @endforelse
        </ul>
    </x-card>
</x-page-shell>

{{-- Ad-hoc-Dialoge (lokale <dialog>-Modals, geöffnet via data-open-dialog) --}}
@if ($isEditable && $canUpdate)
    @include('disposal._item_dialog', ['job' => $job, 'item' => null, 'wasteCodes' => $wasteCodes])
    @foreach ($job->items as $item)
        @include('disposal._item_dialog', ['job' => $job, 'item' => $item, 'wasteCodes' => $wasteCodes])
        @include('disposal._treatment_dialog', ['item' => $item, 'users' => $users])
    @endforeach
@endif
@if ($canHandover && $canUpdate)
    @include('disposal._handover_dialog', ['job' => $job, 'disposers' => $disposers])
@endif
@if ($job->status->isOpen())
    @can('complete', $job)
        @include('disposal._cancel_dialog', ['job' => $job])
    @endcan
@endif
@endsection
