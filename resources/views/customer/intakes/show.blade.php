{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Eingang aus Kundensicht (MVP-1074–1077) — erwartet: $intake, $stage, $messages (nur
  kundensichtbare), $files (nur kundensichtbare), $quote (?Quote), $quoteDecidable,
  $target (?Model), $targetPanel (?string). Interne Notizen und Dateien erscheinen nie.
--}}
@extends('customer.layout')

@section('content')
    <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
        <div>
            <p class="font-mono text-sm text-muted">{{ $intake->number }} · {{ $intake->kind->label() }}</p>
            <h1 class="text-2xl font-semibold">{{ $intake->subject }}</h1>
        </div>
        <x-status-badge :tone="$stage->tone">{{ $stage->label }}</x-status-badge>
    </div>

    @if ($stage->nextStep !== null)
        <div role="status" class="alert alert-warning mb-4 text-sm">
            <x-icon name="priority_high" />
            <span>{{ $stage->nextStep }}</span>
        </div>
    @endif
    @if ($intake->status === \App\Enums\Customer\IntakeStatus::Rejected && $intake->rejection_reason !== null)
        <div role="status" class="alert alert-error mb-4 text-sm">
            <span><strong>{{ __('customer_intake.field.rejection_reason') }}:</strong> {{ $intake->rejection_reason }}</span>
        </div>
    @endif

    <div class="space-y-4">
        <x-card :title="__('customer_intake.portal.your_request')">
            <x-detail-grid class="grid-cols-1 sm:grid-cols-2">
                <x-detail-grid.row :label="__('customer_intake.field.received_at')">{{ $intake->created_at?->fdatetime() }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('customer_intake.field.desired_date')">{{ $intake->desired_date?->fdate() ?? '—' }}</x-detail-grid.row>
                @if ($intake->requestItem !== null)
                    <x-detail-grid.row :label="__('customer_intake.field.catalog_item')">{{ $intake->requestItem->name }}</x-detail-grid.row>
                @endif
                @if ($intake->asset !== null)
                    <x-detail-grid.row :label="__('customer_intake.field.asset')">{{ $intake->asset->name }}</x-detail-grid.row>
                @endif
            </x-detail-grid>
            @if ($intake->description !== null)
                <p class="my-3 whitespace-pre-line text-sm">{{ $intake->description }}</p>
            @endif
            @include('customer-intakes._answers', ['document' => $intake->form])
            @if ($intake->catalog_form !== null && ! $intake->catalog_form->schema->isEmpty())
                <div class="mt-3">@include('customer-intakes._answers', ['document' => $intake->catalog_form])</div>
            @endif
            <p class="mt-3 text-xs text-muted">{{ __('customer_intake.portal.snapshot_hint') }}</p>
        </x-card>

        @if ($quote !== null)
            <x-card :title="__('customer_intake.portal.quote_title', ['number' => $quote->number, 'version' => $quote->version])">
                @if ($quote->valid_until !== null)
                    <p class="mb-2 text-sm text-base-content/70">{{ __('Gültig bis :date', ['date' => $quote->valid_until->fdate()]) }}</p>
                @endif
                @if ($quote->status === \App\Enums\Sales\QuoteStatus::Sent && ! $quoteDecidable && ! $quote->isExpired())
                    <div role="status" class="alert alert-info mb-3 text-sm">{{ __('customer_intake.portal.quote_revised') }}</div>
                @endif
                @include('quotes._customer_decision', [
                    'quote' => $quote,
                    'action' => $quoteDecidable && $intake->status->isOpen() ? route('customer.intakes.quote.decide', $intake) : null,
                    'hidden' => [],
                    'withReason' => true,
                ])
                @error('decision')<p class="text-error mt-2 text-sm">{{ $message }}</p>@enderror
            </x-card>
        @endif

        @if ($targetPanel !== null)
            @include($targetPanel, ['intake' => $intake, 'target' => $target])
        @endif

        <x-card :title="__('customer_intake.portal.messages_title')">
            <div class="space-y-3">
                @forelse ($messages as $message)
                    <div class="rounded-box border border-base-300 p-3 text-sm">
                        <p class="mb-1 text-xs text-muted">
                            <span class="font-semibold text-base-content">{{ $message->kind === \App\Enums\Customer\IntakeMessageKind::Reply ? ($message->author?->name ?? __('Sie')) : __('customer_intake.portal.from_us') }}</span>
                            · {{ $message->created_at?->fdatetime() }}
                        </p>
                        <p class="whitespace-pre-line">{{ $message->body }}</p>
                    </div>
                @empty
                    <p class="text-sm text-muted">{{ __('customer_intake.portal.no_messages') }}</p>
                @endforelse
            </div>

            @if ($intake->status->isOpen())
                <form method="POST" action="{{ route('customer.intakes.reply', $intake) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf
                    <div class="fieldset">
                        <label class="fieldset-label" for="intake-reply">{{ __('customer_intake.portal.reply_label') }}</label>
                        <textarea id="intake-reply" name="body" rows="3" required maxlength="5000" class="textarea textarea-bordered w-full @error('body') textarea-error @enderror">{{ old('body') }}</textarea>
                        <p class="mt-1 text-xs text-muted">{{ __('customer_intake.portal.immutable_hint') }}</p>
                        @error('body')<p class="text-error text-sm">{{ $message }}</p>@enderror
                    </div>
                    <x-upload-input :purpose="$intake->kind->uploadPurpose()" :label="__('customer_intake.field.files_optional')" id="reply-uploads" />
                    <div class="flex justify-end">
                        <x-button type="submit" tone="primary" icon="send"><span>{{ __('customer_intake.portal.reply_submit') }}</span></x-button>
                    </div>
                </form>
            @endif
        </x-card>

        <x-card :title="__('customer_intake.section.files')">
            <x-table :bare="true" size="sm">
                <x-slot:head>
                    <tr>
                        <th>{{ __('customer_intake.field.file') }}</th>
                        <th>{{ __('customer_intake.field.uploaded_at') }}</th>
                        <th class="text-right">{{ __('customer_intake.field.size') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @forelse ($files as $file)
                    <tr>
                        <td class="max-w-64 truncate" title="{{ $file->original_name }}">{{ $file->original_name }}</td>
                        <td class="whitespace-nowrap">{{ $file->created_at?->fdatetime() }}</td>
                        <td class="text-right tabular-nums whitespace-nowrap">{{ \CommonToolkit\ValueObjects\ByteSize::ofBytes((int) $file->size)->format(1) }}</td>
                        <td class="text-right"><x-icon-btn icon="download" size="xs" :href="route('customer.intakes.files.download', [$intake, $file])" :label="__('Herunterladen')" /></td>
                    </tr>
                @empty
                    <x-table.empty :colspan="4" :title="__('customer_intake.files.empty')" compact />
                @endforelse
            </x-table>

            @if ($intake->acceptsCustomerFiles())
                <form method="POST" action="{{ route('customer.intakes.files.store', $intake) }}" enctype="multipart/form-data" class="mt-4 space-y-3" data-upload-form>
                    @csrf
                    <x-upload-input :purpose="$intake->kind->uploadPurpose()" :label="__('customer_intake.portal.add_files')" id="intake-files" required />
                    <div class="flex justify-end">
                        <x-button type="submit" icon="upload"><span>{{ __('customer_intake.portal.upload_submit') }}</span></x-button>
                    </div>
                </form>
            @endif

            {{-- Upload-Kanal (MVP-1078): große Dateien ohne Grenzen des Browser-Uploads. --}}
            @if ($uploadLink !== null && $intake->acceptsCustomerFiles())
                <div class="mt-4 rounded-box border border-base-300 p-3 text-sm">
                    <p class="font-semibold">{{ __('customer_intake.cloud.portal_title', ['channel' => $uploadChannel?->label() ?? $uploadLink->channel]) }}</p>
                    <p class="mt-1">{{ __('customer_intake.cloud.portal_text') }}</p>
                    <x-detail-grid class="mt-2 grid-cols-1 sm:grid-cols-2">
                        <x-detail-grid.row :label="__('customer_intake.cloud.link')">
                            <a class="link break-all" href="{{ $uploadLink->url }}" target="_blank" rel="noopener noreferrer">{{ $uploadLink->url }}</a>
                        </x-detail-grid.row>
                        <x-detail-grid.row :label="__('customer_intake.cloud.password')"><span class="font-mono">{{ $uploadLink->password }}</span></x-detail-grid.row>
                        <x-detail-grid.row :label="__('customer_intake.cloud.expires_at')">{{ $uploadLink->expires_at?->fdate() ?? '—' }}</x-detail-grid.row>
                        <x-detail-grid.row :label="__('customer_intake.cloud.last_synced')">{{ $uploadLink->last_synced_at?->fdatetime() ?? '—' }}</x-detail-grid.row>
                    </x-detail-grid>
                    <form method="POST" action="{{ route('customer.intakes.upload-link.sync', $intake) }}" class="mt-3 flex justify-end">
                        @csrf
                        <x-button type="submit" tone="outline" icon="cloud_download"><span>{{ __('customer_intake.cloud.sync_now') }}</span></x-button>
                    </form>
                </div>
            @elseif ($uploadChannel !== null)
                <form method="POST" action="{{ route('customer.intakes.upload-link', $intake) }}" class="mt-4 flex flex-wrap items-center justify-end gap-2">
                    @csrf
                    <span class="text-xs text-muted">{{ __('customer_intake.cloud.open_hint') }}</span>
                    <x-button type="submit" tone="outline" icon="cloud_upload"><span>{{ __('customer_intake.cloud.open', ['channel' => $uploadChannel->label()]) }}</span></x-button>
                </form>
            @endif
            @error('uploads')<p class="text-error mt-2 text-sm">{{ $message }}</p>@enderror
        </x-card>

        @if ($intake->canBeWithdrawn())
            <form method="POST" action="{{ route('customer.intakes.withdraw', $intake) }}" class="flex flex-wrap items-center justify-end gap-2">
                <span class="text-xs text-muted">{{ __('customer_intake.portal.withdraw_hint') }}</span>
                @csrf
                <x-button type="submit" tone="ghost" icon="undo" class="text-error">{{ __('customer_intake.portal.withdraw') }}</x-button>
            </form>
        @endif
    </div>
@endsection
