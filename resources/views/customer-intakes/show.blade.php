{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Akte eines Kundeneingangs (MVP-1074–1077): Angaben, Dateien, Rückfragen, Angebot, Übernahme, Verlauf. --}}
@extends('layouts.app')

@section('title', $intake->number)
@section('nav-title', __('customer_intake.title'))

@section('content')
@php
    use App\Enums\Customer\{IntakeMessageKind, IntakeStatus};
    $open = $intake->status->isOpen();
    $quote = $intake->quote;
    $quoteChangeable = $open && $quotesAvailable && ! ($quote?->status->isWon() ?? false);
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar back-route="customer-intakes.index" :back-label="__('Zur Liste')">
            <x-slot:title>{{ $intake->number }} — {{ $intake->subject }}</x-slot:title>
            <x-slot:badges>
                <x-status-badge size="sm" outline :tone="$intake->status->tone()">{{ $intake->status->label() }}</x-status-badge>
                <x-status-badge size="sm" outline>{{ $intake->kind->label() }}</x-status-badge>
                @if ($intake->mail_failed_at !== null)
                    <x-status-badge size="sm" outline tone="error">{{ __('customer_intake.badge.mail_failed') }}</x-status-badge>
                @endif
            </x-slot:badges>
            <x-slot:actions>
                @can('update', $intake)
                    @if ($open)
                        <x-icon-btn placement="bar" icon="contact_support" size="sm" data-entry-modal-trigger :href="route('customer-intakes.message.form', [$intake, 'kind' => 'question'])" show-label>{{ __('customer_intake.action.ask') }}</x-icon-btn>
                    @endif
                @endcan
                @can('handover', $intake)
                    @if ($open)
                        <x-icon-btn placement="bar" icon="forward" tone="primary" size="sm" data-entry-modal-trigger :href="route('customer-intakes.handover.form', $intake)" show-label>{{ __('customer_intake.action.handover') }}</x-icon-btn>
                    @endif
                @endcan
                @can('update', $intake)
                    <x-icon-btn placement="menu" icon="person_add" size="sm" data-entry-modal-trigger :href="route('customer-intakes.assign.form', $intake)" show-label>{{ __('customer_intake.action.assign') }}</x-icon-btn>
                    <x-icon-btn placement="menu" icon="sticky_note_2" size="sm" data-entry-modal-trigger :href="route('customer-intakes.message.form', [$intake, 'kind' => 'note'])" show-label>{{ __('customer_intake.action.note') }}</x-icon-btn>
                    @if ($quoteChangeable)
                        <x-icon-btn placement="menu" icon="link" size="sm" data-entry-modal-trigger :href="route('customer-intakes.quote.form', $intake)" show-label>{{ __('customer_intake.action.link_quote') }}</x-icon-btn>
                    @endif
                    @if ($open && $quoteDecidable)
                        <x-action-form :action="route('customer-intakes.quote.announce', $intake)">
                            <x-icon-btn placement="menu" icon="mark_email_unread" size="sm" type="submit" show-label>{{ __('customer_intake.action.announce_quote') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                    @if ($intake->status === IntakeStatus::HandedOver)
                        <x-action-form :action="route('customer-intakes.upload-channel', $intake)">
                            <input type="hidden" name="open" value="{{ $intake->is_upload_open ? 0 : 1 }}">
                            <x-icon-btn placement="menu" :icon="$intake->is_upload_open ? 'lock' : 'lock_open'" size="sm" type="submit" show-label>{{ $intake->is_upload_open ? __('customer_intake.action.close_channel') : __('customer_intake.action.open_channel') }}</x-icon-btn>
                        </x-action-form>
                    @endif
                    @if ($open && ! ($quote?->status->isWon() ?? false))
                        <x-icon-btn placement="danger" icon="block" size="sm" data-entry-modal-trigger :href="route('customer-intakes.reject.form', $intake)" show-label>{{ __('customer_intake.action.reject') }}</x-icon-btn>
                    @endif
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-validation-errors />

    @if ($open && $blockers !== [])
        <div role="status" class="alert alert-info text-sm">
            <x-icon name="info" />
            <div>
                <p class="font-semibold">{{ __('customer_intake.handover.not_yet') }}</p>
                <ul class="list-inside list-disc">
                    @foreach ($blockers as $blocker)
                        <li>{{ $blocker }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('customer_intake.section.intake')" icon="move_to_inbox">
            <x-detail-grid class="grid-cols-2">
                <x-detail-grid.row :label="__('Kunde')">
                    @if ($intake->customer !== null)
                        <a href="{{ route('customers.show', $intake->customer) }}" class="link">{{ $intake->customer->name }}</a>
                    @else
                        —
                    @endif
                </x-detail-grid.row>
                <x-detail-grid.row :label="__('customer_intake.field.submitter')">{{ $intake->submitter?->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('customer_intake.field.received_at')">{{ $intake->created_at?->fdatetime() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('customer_intake.field.desired_date')">{{ $intake->desired_date?->fdate() ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('customer_intake.field.assignee')">{{ $intake->assignee?->name ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('customer_intake.field.customer_view')">
                    <x-status-badge size="sm" outline :tone="$stage->tone">{{ $stage->label }}</x-status-badge>
                </x-detail-grid.row>
                @if ($intake->requestItem !== null)
                    <x-detail-grid.row :label="__('customer_intake.field.catalog_item')">{{ $intake->requestItem->name }}</x-detail-grid.row>
                @endif
                @if ($intake->asset !== null)
                    <x-detail-grid.row :label="__('customer_intake.field.asset')">{{ $intake->asset->name }}</x-detail-grid.row>
                @endif
                @if ($intake->rejection_reason !== null)
                    <x-detail-grid.row :label="__('customer_intake.field.rejection_reason')" class="col-span-2">{{ $intake->rejection_reason }}</x-detail-grid.row>
                @endif
                @if ($target !== null)
                    <x-detail-grid.row :label="__('customer_intake.field.target')">
                        @if ($targetUrl !== null)
                            <a href="{{ $targetUrl }}" class="link">{{ $targetLabel }}</a>
                        @else
                            {{ $targetLabel }}
                        @endif
                    </x-detail-grid.row>
                    <x-detail-grid.row :label="__('customer_intake.field.handed_over_at')">{{ $intake->handed_over_at?->fdatetime() }} · {{ $intake->handoverUser?->name }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('customer_intake.field.upload_channel')">{{ $intake->is_upload_open ? __('customer_intake.channel.open') : __('customer_intake.channel.closed') }}</x-detail-grid.row>
                @endif
            </x-detail-grid>
        </x-card>

        <x-card :title="__('customer_intake.section.request')" icon="description">
            @if ($intake->description !== null)
                <p class="mb-3 whitespace-pre-line text-sm">{{ $intake->description }}</p>
            @endif
            @include('customer-intakes._answers', ['document' => $intake->form])
            @if ($intake->catalog_form !== null && ! $intake->catalog_form->schema->isEmpty())
                <h3 class="mt-4 mb-2 text-sm font-semibold">{{ __('customer_intake.section.catalog_answers') }}</h3>
                @include('customer-intakes._answers', ['document' => $intake->catalog_form])
            @endif
        </x-card>

        @if ($quotesAvailable)
            <x-card :title="__('customer_intake.section.quote')" icon="request_quote">
                @if ($quote !== null)
                    <x-detail-grid class="grid-cols-2">
                        <x-detail-grid.row :label="__('Angebot')">
                            @can('view', $quote)
                                <a href="{{ route('quotes.show', $quote) }}" class="link font-mono">{{ $quote->number }}</a>
                            @else
                                <span class="font-mono">{{ $quote->number }}</span>
                            @endcan
                            · {{ __('customer_intake.quote.version', ['version' => $quote->version]) }}
                        </x-detail-grid.row>
                        <x-detail-grid.row :label="__('Status')">{{ $quote->status->label() }}</x-detail-grid.row>
                        <x-detail-grid.row :label="__('customer_intake.quote.total')">{{ $quote->total?->format() ?? '—' }}</x-detail-grid.row>
                        <x-detail-grid.row :label="__('customer_intake.quote.valid_until')">{{ $quote->valid_until?->fdate() ?? '—' }}</x-detail-grid.row>
                    </x-detail-grid>
                    @if ($quoteSuperseded)
                        <p class="mt-3 text-sm text-warning">{{ __('customer_intake.quote.superseded_hint') }}</p>
                    @endif
                    @if (! $quoteDecidable && in_array($quote->status, [\App\Enums\Sales\QuoteStatus::Draft, \App\Enums\Sales\QuoteStatus::Approved], true))
                        <p class="mt-3 text-sm text-muted">{{ __('customer_intake.quote.send_hint') }}</p>
                    @endif
                    @can('update', $intake)
                        @if ($quoteChangeable)
                            <x-action-form :action="route('customer-intakes.quote.unlink', $intake)" method="DELETE" class="mt-3"
                                           :confirm="__('customer_intake.quote.unlink_confirm')">
                                <x-button type="submit" tone="ghost" size="sm" icon="link_off">{{ __('customer_intake.action.unlink_quote') }}</x-button>
                            </x-action-form>
                        @endif
                    @endcan
                @else
                    <x-empty-state compact icon="request_quote" :title="__('customer_intake.quote.none')" />
                    @can('update', $intake)
                        @if ($quoteChangeable)
                            <div class="mt-3 flex flex-wrap gap-2">
                                @can('create', \App\Models\Sales\Quote::class)
                                    <x-action-form :action="route('customer-intakes.quote.create', $intake)">
                                        <x-button type="submit" tone="primary" size="sm" icon="add">{{ __('customer_intake.action.create_quote') }}</x-button>
                                    </x-action-form>
                                @endcan
                                <x-button size="sm" icon="link" data-entry-modal-trigger :href="route('customer-intakes.quote.form', $intake)">{{ __('customer_intake.action.link_quote') }}</x-button>
                            </div>
                        @endif
                    @endcan
                @endif
            </x-card>
        @endif

        <x-card :title="__('customer_intake.section.files')" icon="attach_file" :count="$files->count()">
            <x-table :bare="true" size="sm">
                <x-slot:head>
                    <tr>
                        <th>{{ __('customer_intake.field.file') }}</th>
                        <th>{{ __('customer_intake.field.uploaded_by') }}</th>
                        <th>{{ __('customer_intake.field.visibility') }}</th>
                        <th class="text-right">{{ __('customer_intake.field.size') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @forelse ($files as $file)
                    <tr>
                        <td class="max-w-64 truncate" title="{{ $file->original_name }}">{{ $file->original_name }}<br><span class="text-xs text-muted">{{ $file->created_at?->fdatetime() }}</span></td>
                        <td>{{ $file->uploader?->name ?? '—' }}@if ($file->uploader?->customer_id !== null) <span class="text-xs text-muted">({{ __('Kunde') }})</span>@endif</td>
                        <td>{{ $file->customer_visible ? __('customer_intake.visibility.customer') : __('customer_intake.visibility.internal') }}</td>
                        <td class="text-right tabular-nums whitespace-nowrap">{{ \CommonToolkit\ValueObjects\ByteSize::ofBytes((int) $file->size)->format(1) }}</td>
                        <td class="text-right"><x-icon-btn icon="download" size="xs" :href="route('customer-intakes.files.download', [$intake, $file])" :label="__('Herunterladen')" /></td>
                    </tr>
                @empty
                    <x-table.empty icon="attach_file" :colspan="5" :title="__('customer_intake.files.empty')" compact />
                @endforelse
            </x-table>

            {{-- Upload-Kanal (MVP-1078): Stand, Fehler, Abholen und Widerruf. --}}
            @if ($uploadLink !== null)
                <div class="mt-4 rounded-box border border-base-300 p-3 text-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold">{{ __('customer_intake.cloud.internal_title', ['channel' => \Illuminate\Support\Str::ucfirst($uploadLink->channel)]) }}</span>
                        @if ($uploadLink->revoked_at !== null)
                            <x-status-badge size="sm" outline>{{ __('customer_intake.cloud.state.revoked', ['date' => $uploadLink->revoked_at->fdatetime()]) }}</x-status-badge>
                        @elseif (! $uploadLink->isActive())
                            <x-status-badge size="sm" outline tone="warning">{{ __('customer_intake.cloud.state.expired') }}</x-status-badge>
                        @else
                            <x-status-badge size="sm" outline tone="success">{{ __('customer_intake.cloud.state.active', ['date' => $uploadLink->expires_at?->fdate() ?? '—']) }}</x-status-badge>
                        @endif
                        @if ($uploadLink->last_error !== null)
                            <x-status-badge size="sm" outline tone="error">{{ __('customer_intake.cloud.state.error') }}</x-status-badge>
                        @endif
                    </div>
                    <x-detail-grid class="mt-2 grid-cols-2">
                        <x-detail-grid.row :label="__('customer_intake.cloud.folder')"><span class="break-all">{{ $uploadLink->folder }}</span></x-detail-grid.row>
                        <x-detail-grid.row :label="__('customer_intake.cloud.last_synced')">{{ $uploadLink->last_synced_at?->fdatetime() ?? '—' }}</x-detail-grid.row>
                        <x-detail-grid.row :label="__('customer_intake.cloud.processed')">{{ count((array) $uploadLink->processed_keys) }}</x-detail-grid.row>
                        @if ($uploadLink->last_error !== null)
                            <x-detail-grid.row :label="__('customer_intake.cloud.last_error')" class="col-span-2"><span class="break-all text-error">{{ $uploadLink->last_error }}</span></x-detail-grid.row>
                        @endif
                    </x-detail-grid>
                    @can('update', $intake)
                        @if ($uploadLink->revoked_at === null)
                            <div class="mt-3 flex flex-wrap justify-end gap-2">
                                <x-action-form :action="route('customer-intakes.upload-link.sync', $intake)">
                                    <x-button type="submit" size="sm" tone="outline" icon="cloud_download">{{ __('customer_intake.cloud.sync_now_internal') }}</x-button>
                                </x-action-form>
                                <x-action-form :action="route('customer-intakes.upload-link.revoke', $intake)" method="DELETE" :confirm="__('customer_intake.cloud.revoke_confirm')">
                                    <x-button type="submit" size="sm" tone="ghost" icon="link_off">{{ __('customer_intake.cloud.revoke') }}</x-button>
                                </x-action-form>
                            </div>
                        @endif
                    @endcan
                </div>
            @endif
        </x-card>

        <x-card :title="__('customer_intake.section.messages')" icon="forum" :count="$intake->messages->count()" class="lg:col-span-2">
            <div class="space-y-3">
                @forelse ($intake->messages as $message)
                    <div @class(['rounded-box border p-3 text-sm', 'border-warning/40 bg-warning/5' => $message->kind === IntakeMessageKind::Note, 'border-base-300' => $message->kind !== IntakeMessageKind::Note])>
                        <div class="mb-1 flex flex-wrap items-center gap-2 text-xs text-muted">
                            <x-status-badge size="sm" outline :tone="$message->kind === IntakeMessageKind::Note ? 'warning' : 'neutral'">{{ $message->kind->label() }}</x-status-badge>
                            <span class="font-medium text-base-content">{{ $message->author?->name ?? '—' }}</span>
                            <span>·</span>
                            <span>{{ $message->created_at?->fdatetime() }}</span>
                        </div>
                        <p class="whitespace-pre-line">{{ $message->body }}</p>
                    </div>
                @empty
                    <x-empty-state compact icon="forum" :title="__('customer_intake.messages.empty')" />
                @endforelse
            </div>
        </x-card>
    </div>

    @if ($targetPanel !== null)
        @include($targetPanel, ['intake' => $intake, 'target' => $target, 'files' => $files])
    @endif

    <x-card :title="__('Verlauf')" icon="history" :count="$intake->journal->count()">
        <x-journal :entries="$intake->journal" :empty-text="__('Keine Ereignisse.')" />
    </x-card>
</x-page-shell>
@endsection
