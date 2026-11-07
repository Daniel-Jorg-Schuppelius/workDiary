{{--
  Created on   : Sat Nov 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _supporting_documents.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- In der E-Rechnung eingebettete Unterlagen (BG-24, Feature 196). Links werden nie abgerufen. Erwartet: $incoming --}}
@php
    $embedded = (array) ($incoming->summary['supporting_documents'] ?? []);
    $notices = (array) ($incoming->summary['supporting_notices'] ?? []);
    $pdfNames = (array) ($incoming->summary['pdf_attachments'] ?? []);
    $attachmentsById = $incoming->attachments->keyBy('id');
@endphp
@if ($embedded !== [] || $notices !== [] || $pdfNames !== [])
    <x-card :title="__('invoicing.incoming_documents.heading')" icon="attach_file" padding="p-0">
        @if ($embedded !== [])
            <x-table>
                <x-slot:head>
                    <tr>
                        <th>{{ __('invoicing.supporting_documents.reference') }}</th>
                        <th>{{ __('invoicing.supporting_documents.filename') }}</th>
                        <th>{{ __('invoicing.supporting_documents.description') }}</th>
                        <th class="text-right">{{ __('invoicing.supporting_documents.size') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($embedded as $row)
                    @php $attachment = ($row['attachment_id'] ?? null) !== null ? $attachmentsById->get((int) $row['attachment_id']) : null; @endphp
                    <tr>
                        <td class="whitespace-nowrap">{{ $row['reference'] ?? '—' }}</td>
                        <td class="max-w-xs truncate">
                            @if (! empty($row['external_url']))
                                <x-external-link :url="$row['external_url']" :label="(string) parse_url((string) $row['external_url'], PHP_URL_HOST)" />
                            @else
                                {{ $row['filename'] ?? '—' }}
                            @endif
                        </td>
                        <td class="max-w-sm truncate">{{ $row['description'] ?? '' }}</td>
                        <td class="whitespace-nowrap text-right">{{ ($row['size'] ?? 0) > 0 ? \CommonToolkit\Helper\Data\NumberHelper::formatBytes((int) $row['size'], 1) : '—' }}</td>
                        <td class="whitespace-nowrap text-right">
                            <div class="flex justify-end gap-1">
                                @if (! empty($row['infected']))<x-status-badge tone="error" size="xs">{{ __('invoicing.incoming_documents.flag.infected') }}</x-status-badge>@endif
                                @if (! empty($row['encrypted']))<x-status-badge tone="warning" size="xs">{{ __('invoicing.incoming_documents.flag.encrypted') }}</x-status-badge>@endif
                                @if (! empty($row['mime_mismatch']))<x-status-badge tone="warning" size="xs">{{ __('invoicing.incoming_documents.flag.mime_mismatch') }}</x-status-badge>@endif
                                @if (! empty($row['duplicate']))<x-status-badge tone="neutral" outline size="xs">{{ __('invoicing.incoming_documents.flag.duplicate') }}</x-status-badge>@endif
                                @if (($row['skipped'] ?? null) !== null)<x-status-badge tone="neutral" outline size="xs">{{ __('invoicing.incoming_documents.flag.skipped') }}</x-status-badge>@endif
                                @if ($attachment !== null)
                                    <x-icon-btn icon="download" size="xs" :href="\App\Http\Controllers\Attachments\AttachmentController::downloadUrl($attachment)" :title="__('invoicing.supporting_documents.actions.download')" />
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
        <div class="space-y-2 p-3 text-sm">
            @foreach ($notices as $notice)
                <div role="status" class="alert alert-info text-sm"><x-icon name="info" />{{ $notice }}</div>
            @endforeach
            @if ($pdfNames !== [])
                <p class="text-base-content/70">{{ __('invoicing.incoming_documents.pdf_attachments', ['names' => implode(', ', $pdfNames)]) }}</p>
            @endif
            @if (collect($embedded)->contains(static fn ($row): bool => ! empty($row['external_url'])))
                <p class="text-base-content/70">{{ __('invoicing.incoming_documents.link_hint') }}</p>
            @endif
        </div>
    </x-card>
@endif
