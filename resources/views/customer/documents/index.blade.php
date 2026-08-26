{{--
  Created on   : Tue Jul 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Portal-Dokumente (Feature 031/012, Welle D — Dokument-Spiegelung): NUR fürs
  Kundenportal freigegebene Dokumente des eigenen Kunden. Sicherer Download
  hinter dem Portal-Guard.
--}}
@extends('customer.layout')

@section('content')
    <h1 class="text-2xl font-semibold mb-1">{{ __('document.customer.portal.title') }}</h1>
    <p class="text-sm text-muted mb-4">{{ __('document.customer.portal.subtitle') }}</p>

    @php
        // Rückfragen (MVP-512): CTA nur mit eigener Capability.
        $portalQueryCustomer = auth('customer')->user()?->customer;
        $canQuery = $portalQueryCustomer !== null
            && app(\App\Services\CustomerPortal\PortalVisibility::class)->allows($portalQueryCustomer, \App\Enums\CustomerPortal\PortalCapability::Queries);
    @endphp

    <x-table>
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('document.field.title') }}</x-table.th>
                <x-table.th>{{ __('document.field.type') }}</x-table.th>
                <x-table.th>{{ __('document.field.valid_until') }}</x-table.th>
                <x-table.th>{{ __('document.customer.released_at') }}</x-table.th>
                <x-table.th class="text-right">{{ __('document.action.download') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($documents as $document)
            <tr>
                <td class="font-medium">{{ $document->title }}</td>
                <td>{{ $document->document_type->label() }}</td>
                <td class="tabular-nums">{{ $document->valid_until?->fdate() ?? '—' }}</td>
                <td class="tabular-nums text-sm">{{ $document->customer_released_at?->fdate() ?? '—' }}</td>
                <td class="text-right whitespace-nowrap">
                    @if ($canQuery)
                        <a href="{{ route('customer.queries.create', ['subject_type' => 'document', 'subject' => $document->sqid]) }}"
                           class="btn btn-ghost btn-xs">{{ __('Rückfrage') }}</a>
                    @endif
                    @if ($document->currentVersion !== null)
                        <x-button tone="outline" size="xs" icon="download"
                                  :href="route('customer.documents.download', $document)">
                            <span>{{ __('document.action.download') }}</span>
                        </x-button>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="5" :title="__('document.customer.portal.empty')" />
        @endforelse
    </x-table>

    <x-pagination :paginator="$documents" standing />
@endsection
