{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', $supplier->name . ' — ' . __('Lieferant'))
@section('nav-title', $supplier->name)

@section('content')
<x-page-shell>
    {{-- Header --}}
    <x-entity-header :title="$supplier->name" :color="$supplier->color"
                     :back-route="route('suppliers.index')"
                     :edit-route="route('suppliers.edit', $supplier)"
                     :archived="$supplier->isArchived()"
                     :restore-route="route('suppliers.restore', $supplier)"
                     :archive-route="route('suppliers.archive', $supplier)"
                     :can-manage="auth()->user()->can('update', $supplier)">
        <x-slot:badges>
            @if ($supplier->isArchived())
                <x-status-badge tone="ghost">{{ __('archiviert') }}</x-status-badge>
            @endif
            @unless ($supplier->active)
                <x-status-badge tone="warning">{{ __('inaktiv') }}</x-status-badge>
            @endunless
        </x-slot:badges>
        <x-slot:meta>
            @if ($supplier->company){{ $supplier->company }} · @endif
            @if ($supplier->number){{ __('Nr.') }} {{ $supplier->number }} · @endif
            {{ $supplier->currency }}
        </x-slot:meta>
        @if ($tags->isNotEmpty())
            <x-slot:tags>
                @foreach ($tags as $tag)
                    <x-tag-badge :tag="$tag" />
                @endforeach
            </x-slot:tags>
        @endif
    </x-entity-header>

    {{-- Stammdaten --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-card :title="__('Kontakt')" icon="contacts">
            <x-detail-grid>
                <x-detail-grid.row :label="__('Ansprechpartner')" :value="$supplier->contact_name" />
                <x-detail-grid.row :label="__('E-Mail')">@if ($supplier->email)<a class="link" href="mailto:{{ $supplier->email }}">{{ $supplier->email }}</a>@endif</x-detail-grid.row>
                <x-detail-grid.row :label="__('Telefon')" :value="$supplier->phone" />
                <x-detail-grid.row :label="__('Mobil')" :value="$supplier->mobile" />
                <x-detail-grid.row :label="__('Homepage')">@if ($supplier->homepage)<a class="link" href="{{ $supplier->homepage }}" target="_blank" rel="noopener">{{ $supplier->homepage }}</a>@endif</x-detail-grid.row>
                @if ($supplier->address_street || $supplier->address_zip || $supplier->address_city)
                    <dt class="text-base-content/60">{{ __('Adresse') }}</dt>
                    <dd class="whitespace-pre-line">{!! e($supplier->address_street) !!}@if($supplier->address_street)
@endif{{ trim(($supplier->address_zip ?? '').' '.($supplier->address_city ?? '')) }}</dd>
                @elseif ($supplier->address)
                    <dt class="text-base-content/60">{{ __('Adresse') }}</dt><dd class="whitespace-pre-line">{{ $supplier->address }}</dd>
                @endif
                <x-detail-grid.row :label="__('Land')" :value="$supplier->country" />
            </x-detail-grid>
            <x-contact-persons :persons="$supplier->contact_persons" />
        </x-card>

        <x-card :title="__('Geschäftsdaten')" icon="store">
            <x-detail-grid>
                <x-detail-grid.row :label="__('Aktiv')" :value="$supplier->active ? __('Ja') : __('Nein')" />
                <x-detail-grid.row :label="__('USt-IdNr.')" :value="$supplier->vat_id" />
                <x-detail-grid.row :label="__('Lieferantennr. (Lexoffice)')" :value="$supplier->vendor_number" />
                <x-detail-grid.row :label="__('Währung')" :value="$supplier->currency" />
                <x-detail-grid.row :label="__('Zeitzone')" :value="$supplier->timezone" />
            </x-detail-grid>
            @php $bank = $supplier->bankDetails(); @endphp
            @if ($bank['has_any'])
                <div class="pt-3 border-t border-base-300">
                    <h3 class="mb-1 text-sm font-semibold">{{ __('Bankverbindung') }}</h3>
                    <x-detail-grid>
                        <x-detail-grid.row :label="__('Kontoinhaber')" :value="$bank['holder']" />
                        <x-detail-grid.row :label="__('IBAN')" :value="$bank['iban']" class="tabular-nums" />
                        <x-detail-grid.row :label="__('BIC')" :value="$bank['bic']" />
                        <x-detail-grid.row :label="__('Bank')" :value="$bank['bank']" />
                    </x-detail-grid>
                </div>
            @endif
            @if ($supplier->comment)
                <div class="pt-3 text-sm">
                    <div class="text-base-content/60">{{ __('Notiz') }}</div>
                    <p class="whitespace-pre-line">{{ $supplier->comment }}</p>
                </div>
            @endif
        </x-card>
    </div>

    {{-- Lexoffice --}}
    @if ($lexofficePlugin && $lexofficePlugin->isEnabled())
        <x-card>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                    <x-icon name="sync" class="text-base-content/60" /> {{ __('Lexoffice') }}
                </h2>
                @if ($lexofficeContactRef)
                    <x-status-badge tone="success">{{ __('Kontakt verknüpft') }} · {{ Str::limit($lexofficeContactRef->external_id, 8, '…') }}</x-status-badge>
                @else
                    <x-status-badge tone="ghost">{{ __('Noch nicht verknüpft') }}</x-status-badge>
                @endif
            </div>
        </x-card>
    @endif

    {{-- Anhänge --}}
    <x-attachments-section :attachments="$attachments" upload-type="supplier"
                           :upload-id="$supplier->sqid" :can-upload="auth()->user()->can('update', $supplier)" />

    {{-- Lexoffice-Belege (Rechnungen/Aufträge/Angebote …), zeitraumgefiltert --}}
    @include('partials._lexoffice_vouchers', [
        'plugin' => $lexofficePlugin,
        'contactRef' => $lexofficeContactRef,
        'vouchers' => $lexofficeVoucherCache,
        'range' => $lexofficeVoucherRange,
        'syncRoute' => route('suppliers.lexoffice.sync-vouchers', $supplier),
    ])

    {{-- Verlauf --}}
    @if ($auditLogs->isNotEmpty())
    <x-card :title="__('Verlauf')" icon="history">
        <x-audit-log-list :logs="$auditLogs" />
    </x-card>
    @endif
</x-page-shell>
@endsection
