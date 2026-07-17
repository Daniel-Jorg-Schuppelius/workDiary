{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', $customer->name . ' — ' . __('Kunde'))
@section('nav-title', $customer->name)

@section('content')
<x-page-shell>
    {{-- Header --}}
    <x-entity-header :title="$customer->name" :color="$customer->color"
                     :back-route="route('customers.index')"
                     :edit-route="route('customers.edit', $customer)"
                     :archived="$customer->isArchived()"
                     :restore-route="route('customers.restore', $customer)"
                     :archive-route="route('customers.archive', $customer)"
                     :can-manage="auth()->user()->can('update', $customer)">
        <x-slot:badges>
            @if ($customer->isArchived())
                <x-status-badge tone="ghost">{{ __('archiviert') }}</x-status-badge>
            @endif
            @unless ($customer->billable)
                <x-status-badge tone="warning">{{ __('nicht abrechenbar') }}</x-status-badge>
            @endunless
        </x-slot:badges>
        <x-slot:meta>
            @if ($customer->company){{ $customer->company }} · @endif
            @if ($customer->number){{ __('Nr.') }} {{ $customer->number }} · @endif
            {{ $customer->currency->value }}
        </x-slot:meta>
        @if ($tags->isNotEmpty())
            <x-slot:tags>
                @foreach ($tags as $tag)
                    <x-tag-badge :tag="$tag" />
                @endforeach
            </x-slot:tags>
        @endif
    </x-entity-header>

    {{-- KPI --}}
    @php $timeFormatted = intdiv($totalMinutes, 60) . ':' . str_pad((string) ($totalMinutes % 60), 2, '0', STR_PAD_LEFT) . ' h'; @endphp
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-kpi-tile :label="__('Projekte')" :value="$projects->count()" tone="neutral" />
        <x-kpi-tile :label="__('Erfasste Zeit')" :value="$timeFormatted" tone="neutral" />
        <x-kpi-tile :label="__('Umsatz (kalk.)')" :value="number_format($totalRate, 2, ',', '.') . ' ' . $customer->currency->value" tone="neutral" />
    </div>

    {{-- Stammdaten --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-card :title="__('Kontakt')" icon="contacts">
            <x-detail-grid>
                <x-detail-grid.row :label="__('Ansprechpartner')" :value="$customer->contact_name" />
                <x-detail-grid.row :label="__('E-Mail')">@if ($customer->email)<a class="link" href="mailto:{{ $customer->email }}">{{ $customer->email }}</a>@endif</x-detail-grid.row>
                <x-detail-grid.row :label="__('Telefon')" :value="$customer->phone" />
                <x-detail-grid.row :label="__('Mobil')" :value="$customer->mobile" />
                <x-detail-grid.row :label="__('Homepage')">@if ($customer->homepage)<a class="link" href="{{ $customer->homepage }}" target="_blank" rel="noopener">{{ $customer->homepage }}</a>@endif</x-detail-grid.row>
                @if ($customer->address_street || $customer->address_zip || $customer->address_city)
                    <x-detail-grid.row :label="__('Adresse')" class="whitespace-pre-line">{!! e($customer->address_street) !!}@if($customer->address_street)
@endif{{ trim(($customer->address_zip ?? '').' '.($customer->address_city ?? '')) }}</x-detail-grid.row>
                @elseif ($customer->address)
                    <x-detail-grid.row :label="__('Adresse')" class="whitespace-pre-line">{{ $customer->address }}</x-detail-grid.row>
                @endif
                <x-detail-grid.row :label="__('Land')" :value="$customer->country" />
            </x-detail-grid>
            <x-contact-persons :persons="$customer->contact_persons" />
        </x-card>

        <x-card :title="__('Abrechnung')" icon="receipt_long">
            <x-detail-grid>
                <x-detail-grid.row :label="__('Abrechenbar')" :value="$customer->billable ? __('Ja') : __('Nein')" />
                <x-detail-grid.row :label="__('USt-IdNr.')" :value="$customer->vat_id" />
                <x-detail-grid.row :label="__('Währung')" :value="$customer->currency->value" />
                <x-detail-grid.row :label="__('Zeitzone')" :value="$customer->timezone" />
                @if ($customer->hourly_rate !== null)
                    <x-detail-grid.row :label="__('Stundensatz')" :value="number_format((float) $customer->hourly_rate, 2, ',', '.').' '.$customer->currency->value" />
                @endif
                @if ($customer->internal_rate !== null)
                    <x-detail-grid.row :label="__('Interner Satz')" :value="number_format((float) $customer->internal_rate, 2, ',', '.').' '.$customer->currency->value" />
                @endif
            </x-detail-grid>
            @if ($customer->invoice_text)
                <div class="pt-2 text-sm">
                    <div class="text-base-content/60">{{ __('Rechnungstext') }}</div>
                    <p class="whitespace-pre-line">{{ $customer->invoice_text }}</p>
                </div>
            @endif
            @php $bank = $customer->bankDetails(); @endphp
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
        </x-card>
    </div>

    {{-- Projekte --}}
    <x-card :title="__('Projekte')" icon="folder" :count="$projects->count()">
        @isset($defaultProject)
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('projects.timesheets.create', $defaultProject)"
                            show-label>{{ __('Stundenzettel') }}</x-icon-btn>
            </x-slot:actions>
        @endisset

        @isset($defaultProject)
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-box border border-primary/30 bg-primary/5 px-3 py-2 text-sm">
                <div class="flex items-center gap-2 min-w-0">
                    <x-icon name="star" class="text-primary" :filled="true" />
                    <a class="link link-hover truncate font-medium" href="{{ route('projects.show', $defaultProject) }}">{{ $defaultProject->name }}</a>
                    <x-status-badge tone="ghost">{{ __('Standardprojekt') }}</x-status-badge>
                </div>
                <span class="text-xs text-base-content/60">{{ __('Auto-Bucket für Ad-hoc-/Notfalleinsätze') }}</span>
            </div>
        @endisset

        @if ($projects->isEmpty())
            <x-empty-state compact icon='<span class="material-symbols-outlined">folder_off</span>'
                           :title="__('Keine Projekte')"
                           :message="__('Diesem Kunden sind noch keine Projekte zugeordnet.')" />
        @else
            <ul class="divide-y divide-base-300">
                @foreach ($projects as $project)
                    <li class="flex items-center justify-between py-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="inline-block h-3 w-3 rounded-full" style="background:{{ $project->color ?: '#94a3b8' }}"></span>
                            <a class="link link-hover truncate" href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
                            @if ($project->is_default)
                                <x-icon name="star" class="text-primary" :filled="true" :title="__('Standardprojekt')" />
                            @endif
                        </div>
                        <x-status-badge :tone="$project->statusTone()">{{ $project->statusLabel() }}</x-status-badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    {{-- Fremdkunden (Endkunden dieser Firma) --}}
    @php
        $foreignCustomers = $customer->foreignCustomers()
            ->whereNull('archived_at')
            ->withCount('projects')
            ->get();
    @endphp
    <x-card>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="flex items-center gap-2 font-semibold">
                <x-icon name="groups" class="text-base-content/60" /> {{ __('Fremdkunden') }}
                <span class="font-normal text-base-content/50">({{ $foreignCustomers->count() }})</span>
            </h2>
            @can('create', App\Models\ForeignCustomer::class)
                <x-icon-btn icon="add" size="xs" data-entry-modal-trigger
                            :href="route('foreign-customers.create', ['customer' => $customer->sqid])"
                            show-label>{{ __('Fremdkunde anlegen') }}</x-icon-btn>
            @endcan
        </div>
        @if ($foreignCustomers->isEmpty())
            <p class="text-sm text-base-content/60">{{ __('Keine Fremdkunden. Endkunden dieser Firma hier erfassen, um Zeiten/Abrechnung pro Endkunde zu trennen.') }}</p>
        @else
            <ul class="divide-y divide-base-200">
                @foreach ($foreignCustomers as $fc)
                    <li class="flex items-center justify-between py-1.5 text-sm">
                        <a class="link link-hover" href="{{ route('foreign-customers.show', $fc) }}">{{ $fc->name }}</a>
                        <span class="text-base-content/50 tabular-nums">{{ trans_choice(':count Projekt|:count Projekte', $fc->projects_count, ['count' => $fc->projects_count]) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    {{-- Auswertung pro Kunde --}}
    @isset($statsTotal)
        <x-card x-data="tabs('month')">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                    <x-icon name="analytics" class="text-base-content/60" /> {{ __('Auswertung') }}
                </h2>
                <div role="tablist" class="tabs tabs-box tabs-sm">
                    <button role="tab" class="tab" :class="tabClass('month')" @click="setTab('month')">{{ __('Aktueller Monat') }}</button>
                    <button role="tab" class="tab" :class="tabClass('total')" @click="setTab('total')">{{ __('Gesamt') }}</button>
                </div>
            </div>
            @foreach (['month' => $statsMonth, 'total' => $statsTotal] as $key => $set)
                <div x-show="isTab('{{ $key }}')" x-cloak>
                    <div class="mb-3 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-box bg-base-200 p-3">
                            <div class="text-xs text-base-content/60">{{ __('Stunden gesamt') }}</div>
                            <div class="font-['Space_Grotesk'] text-xl font-semibold">{{ number_format($set['total_minutes'] / 60, 2, ',', '.') }} h</div>
                        </div>
                        <div class="rounded-box bg-base-200 p-3">
                            <div class="text-xs text-base-content/60">{{ __('davon abrechenbar') }}</div>
                            <div class="font-['Space_Grotesk'] text-xl font-semibold">{{ number_format($set['billable_minutes'] / 60, 2, ',', '.') }} h</div>
                        </div>
                    </div>
                    @if (count($set['by_project']) > 0)
                        <x-table table-sort="client" bare>
                            <x-slot:head>
                                <tr>
                                    <x-table.th sort>{{ __('Projekt') }}</x-table.th>
                                    <x-table.th sort type="number" align="right">{{ __('Stunden') }}</x-table.th>
                                    <x-table.th sort type="number" align="right">{{ __('abrechenbar') }}</x-table.th>
                                </tr>
                            </x-slot:head>
                            @foreach ($set['by_project'] as $row)
                                <tr>
                                    <td>
                                        @if ($row['is_default'])
                                            <x-icon name="star" class="text-primary align-middle" :filled="true" size="1rem" />
                                        @endif
                                        {{ $row['name'] }}
                                    </td>
                                    <td class="text-right" data-sort-value="{{ (float) $row['minutes'] }}">{{ number_format($row['minutes'] / 60, 2, ',', '.') }}</td>
                                    <td class="text-right" data-sort-value="{{ (float) $row['billable_minutes'] }}">{{ number_format($row['billable_minutes'] / 60, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </x-table>
                    @endif
                </div>
            @endforeach
        </x-card>
    @endisset

    {{-- Anhänge --}}
    <x-attachments-section :attachments="$attachments" upload-type="customer"
                           :upload-id="$customer->sqid" :can-upload="auth()->user()->can('update', $customer)" />

    {{-- Änderungsverlauf (Audit) — abgegrenzt vom Aktivitäts-„Verlauf" unten --}}
    @if ($auditLogs->isNotEmpty())
    <x-card :title="__('Änderungsverlauf')" icon="history">
        <x-audit-log-list :logs="$auditLogs" />
    </x-card>
    @endif

    @if ($lexofficePlugin && $lexofficePlugin->isEnabled())
        @can('update', $customer)
        <x-card class="space-y-4">
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

            <div class="grid gap-3 md:grid-cols-2">
                <form method="POST" action="{{ route('customers.lexoffice.contact', $customer) }}"
                      class="flex h-full flex-col gap-3 rounded-box border border-base-300 bg-base-200/40 p-3">
                    @csrf
                    <div class="flex items-center gap-2 text-sm font-semibold">
                        <x-icon name="contacts" class="text-base-content/60" /> {{ __('Kontakt') }}
                    </div>
                    <p class="text-sm text-base-content/70">
                        {{ __('Kunde als Kontakt in Lexoffice anlegen oder aktualisieren.') }}
                    </p>
                    <div class="mt-auto pt-1">
                        <x-icon-btn icon="person_add" tone="primary" size="sm" type="submit" show-label>
                            {{ $lexofficeContactRef ? __('Kontakt aktualisieren') : __('Kontakt anlegen') }}
                        </x-icon-btn>
                    </div>
                </form>

                <form method="POST" action="{{ route('customers.lexoffice.time-export', $customer) }}"
                      class="flex h-full flex-col gap-3 rounded-box border border-base-300 bg-base-200/40 p-3">
                    @csrf
                    <div class="flex items-center gap-2 text-sm font-semibold">
                        <x-icon name="receipt_long" class="text-base-content/60" /> {{ __('Zeiten als Beleg') }}
                    </div>
                    <p class="text-sm text-base-content/70">
                        {{ __('Abrechenbare, noch nicht übertragene Zeiten als Beleg übertragen.') }}
                    </p>
                    <x-date-range
                        :from="now()->startOfMonth()->toDateString()"
                        :to="now()->endOfMonth()->toDateString()"
                        :required="true"
                    />
                    <div class="mt-auto pt-1">
                        <x-icon-btn icon="sync" tone="primary" size="sm" type="submit" show-label>{{ __('Zeiten übertragen') }}</x-icon-btn>
                    </div>
                </form>
            </div>

            @if ($lexofficeVouchers->isNotEmpty())
                <div class="border-t border-base-300 pt-3">
                    <h3 class="mb-2 text-sm font-semibold">{{ __('Letzte Belege') }}</h3>
                    <ul class="divide-y divide-base-300 text-sm">
                        @foreach ($lexofficeVouchers as $ref)
                            <li class="flex items-center justify-between gap-2 py-1.5">
                                <code class="text-xs text-base-content/80">{{ $ref->external_id }}</code>
                                <span class="text-xs text-base-content/60">{{ optional($ref->synced_at)->fdatetime() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-card>
        @endcan
    @endif

    @include('partials._documents', [
        'invoices' => $localInvoices,
        'vouchers' => $lexofficeVoucherCache,
        'plugin' => $lexofficePlugin,
        'contactRef' => $lexofficeContactRef,
        'range' => $lexofficeVoucherRange,
        'syncRoute' => route('customers.lexoffice.sync-vouchers', $customer),
        'placeholder' => true,
    ])

    @include('customers._timeline_panel', ['customer' => $customer])

    @include('communication-notes._panel', ['notable' => $customer, 'notableKind' => 'customer'])

    @include('documents._panel', ['documentable' => $customer, 'documentableKind' => 'customer'])
</x-page-shell>
@endsection
