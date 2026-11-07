{{--
  Created on   : Sat Nov 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _supporting_documents.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Rechnungsbegründende Unterlagen (Feature 196, MVP-1485/1488). Erwartet: $carrier (Rechnung oder externe
     Rechnung), $routePrefix (invoices.supporting-documents | finance.external-invoices.documents). --}}
@php
    /** @var \App\Models\Contracts\SupportingDocumentCarrier&\Illuminate\Database\Eloquent\Model $carrier */
    $documents = $carrier->supportingDocuments()->with('attachment')->get();
    $open = $carrier->supportingDocumentsLockedReason() === null;
    $canEdit = $open && (auth()->user()?->can('update', $carrier) ?? false);
    $profiles = app(\App\Services\Invoicing\EInvoice\RecipientProfiles::class);
    $limit = $profiles->limitBytes($carrier->carrierCustomer(), $carrier->carrierOrganization());
    // Grobe Schätzung für die Anzeige: Base64-Inhalt plus Rechnungsdaten; geprüft wird an der fertigen Fassung.
    $estimate = 32 * 1024 + $documents->sum(fn (\App\Models\Invoicing\InvoiceSupportingDocument $d): int => $d->is_embedded ? 4 * (int) ceil($d->size / 3) : 0);
    $autoAttach = $open && $profiles->attachesActivityReport($carrier->carrierCustomer(), $carrier->carrierOrganization());
    $editable = $documents->reject(fn (\App\Models\Invoicing\InvoiceSupportingDocument $d): bool => $d->isFrozen())->values();
@endphp
@if ($documents->isNotEmpty() || $canEdit)
    <x-card :title="__('invoicing.supporting_documents.heading')" padding="p-0">
        <x-slot:actions>
            @if ($canEdit)
                <x-action-form :action="route($routePrefix . '.generate', $carrier)">
                    <x-icon-btn icon="fact_check" size="sm" type="submit" show-label>{{ __('invoicing.supporting_documents.actions.generate') }}</x-icon-btn>
                </x-action-form>
                <x-icon-btn icon="attach_file" size="sm" data-entry-modal-trigger :href="route($routePrefix . '.upload.form', $carrier)" show-label>{{ __('invoicing.supporting_documents.actions.upload') }}</x-icon-btn>
                <x-icon-btn icon="link" size="sm" data-entry-modal-trigger :href="route($routePrefix . '.link.form', $carrier)" show-label>{{ __('invoicing.supporting_documents.actions.link') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        <div class="space-y-1 px-4 pt-3 text-sm text-base-content/70">
            <p>{{ __('invoicing.supporting_documents.intro') }}</p>
            @if ($autoAttach)
                <p>{{ __('invoicing.supporting_documents.auto_hint') }}</p>
            @endif
            @if ($documents->isNotEmpty())
                <p @class(['text-error' => $estimate > $limit])>
                    {{ __('invoicing.supporting_documents.budget', ['used' => \CommonToolkit\Helper\Data\NumberHelper::formatBytes($estimate, 1), 'limit' => \CommonToolkit\Helper\Data\NumberHelper::formatBytes($limit, 0)]) }}
                    @if ($estimate > $limit) · {{ __('invoicing.supporting_documents.budget_over', ['limit' => \CommonToolkit\Helper\Data\NumberHelper::formatBytes($limit, 0)]) }} @endif
                </p>
            @endif
        </div>

        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('invoicing.supporting_documents.reference') }}</th>
                    <th>{{ __('invoicing.supporting_documents.kind') }}</th>
                    <th>{{ __('invoicing.supporting_documents.filename') }}</th>
                    <th>{{ __('invoicing.supporting_documents.description') }}</th>
                    <th class="text-right">{{ __('invoicing.supporting_documents.size') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($documents as $document)
                <tr>
                    <td class="whitespace-nowrap">{{ $document->reference }}</td>
                    <td class="whitespace-nowrap">{{ $document->kind->label() }}</td>
                    <td class="max-w-xs truncate" title="{{ $document->filename ?? $document->external_url }}">
                        @if ($document->is_embedded)
                            {{ $document->filename }}
                        @else
                            <span class="text-base-content/70">{{ __('invoicing.supporting_documents.link') }}:</span>
                            {{ parse_url((string) $document->external_url, PHP_URL_HOST) }}
                        @endif
                    </td>
                    <td class="max-w-sm">
                        <div class="truncate">{{ $document->description }}</div>
                        @if ($document->transfer_note)<div class="text-xs text-warning">{{ $document->transfer_note }}</div>@endif
                    </td>
                    <td class="whitespace-nowrap text-right">{{ $document->is_embedded ? \CommonToolkit\Helper\Data\NumberHelper::formatBytes((int) $document->size, 1) : '—' }}</td>
                    <td class="whitespace-nowrap text-right">
                        <div class="flex justify-end gap-1">
                            @if ($document->isFrozen())
                                <x-status-badge tone="neutral" outline size="xs" :title="$document->frozen_at->fdatetime()">{{ __('invoicing.supporting_documents.frozen') }}</x-status-badge>
                            @endif
                            @if ($document->is_embedded)
                                <x-icon-btn icon="download" size="xs" :href="route($routePrefix . '.download', [$carrier, $document])" :title="__('invoicing.supporting_documents.actions.download')" />
                            @endif
                            @if ($canEdit && ! $document->isFrozen())
                                @unless ($editable->first()?->is($document))
                                    <x-action-form :action="route($routePrefix . '.move', [$carrier, $document])">
                                        <input type="hidden" name="direction" value="up">
                                        <x-icon-btn icon="arrow_upward" size="xs" type="submit" :title="__('invoicing.supporting_documents.actions.up')" />
                                    </x-action-form>
                                @endunless
                                @unless ($editable->last()?->is($document))
                                    <x-action-form :action="route($routePrefix . '.move', [$carrier, $document])">
                                        <input type="hidden" name="direction" value="down">
                                        <x-icon-btn icon="arrow_downward" size="xs" type="submit" :title="__('invoicing.supporting_documents.actions.down')" />
                                    </x-action-form>
                                @endunless
                                <x-action-form :action="route($routePrefix . '.destroy', [$carrier, $document])" method="DELETE"
                                               :confirm="__('invoicing.supporting_documents.confirm_remove', ['name' => $document->filename ?? $document->description])" confirm-icon="delete" confirm-tone="error">
                                    <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :title="__('invoicing.supporting_documents.actions.remove')" />
                                </x-action-form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty icon="attach_file" :colspan="6" :title="__('invoicing.supporting_documents.empty')" compact />
            @endforelse
        </x-table>
    </x-card>
@endif
