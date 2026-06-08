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
    <x-card>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="inline-block h-3 w-3 rounded-full" style="background:{{ $customer->color ?: '#94a3b8' }}"></span>
                    <h1 class="font-['Space_Grotesk'] text-lg font-semibold truncate">{{ $customer->name }}</h1>
                    @if ($customer->isArchived())
                        <x-status-badge tone="ghost">{{ __('archiviert') }}</x-status-badge>
                    @endif
                    @unless ($customer->billable)
                        <x-status-badge tone="warning">{{ __('nicht abrechenbar') }}</x-status-badge>
                    @endunless
                </div>
                <p class="mt-1 text-sm text-base-content/60">
                    @if ($customer->company){{ $customer->company }} · @endif
                    @if ($customer->number){{ __('Nr.') }} {{ $customer->number }} · @endif
                    {{ $customer->currency }}
                </p>
                @if ($tags->isNotEmpty())
                    <div class="mt-2 flex flex-wrap gap-1">
                        @foreach ($tags as $tag)
                            <span class="badge badge-sm" style="background:{{ $tag->color ?? '#e5e7eb' }};color:#000">{{ $tag->name }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-icon-btn icon="arrow_back" size="sm"
                            :href="route('customers.index')"
                            show-label>{{ __('Zurück') }}</x-icon-btn>
                @can('update', $customer)
                    @if ($customer->isArchived())
                        <form method="POST" action="{{ route('customers.restore', $customer) }}" class="inline">
                            @csrf
                            <x-icon-btn icon="restore" size="sm" type="submit" show-label>{{ __('Wiederherstellen') }}</x-icon-btn>
                        </form>
                    @else
                        <form method="POST" action="{{ route('customers.archive', $customer) }}" class="inline">
                            @csrf
                            <x-icon-btn icon="archive" size="sm" type="submit" show-label>{{ __('Archivieren') }}</x-icon-btn>
                        </form>
                    @endif
                    <x-icon-btn icon="edit" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('customers.edit', $customer)"
                                show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                @endcan
            </div>
        </div>
    </x-card>

    {{-- KPI --}}
    @php $timeFormatted = intdiv($totalMinutes, 60) . ':' . str_pad((string) ($totalMinutes % 60), 2, '0', STR_PAD_LEFT) . ' h'; @endphp
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-kpi-tile :label="__('Projekte')" :value="$projects->count()" tone="neutral" />
        <x-kpi-tile :label="__('Erfasste Zeit')" :value="$timeFormatted" tone="neutral" />
        <x-kpi-tile :label="__('Umsatz (kalk.)')" :value="number_format($totalRate, 2, ',', '.') . ' ' . $customer->currency" tone="neutral" />
    </div>

    {{-- Stammdaten --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-card>
            <h2 class="mb-3 flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                <x-icon name="contacts" class="text-base-content/60" /> {{ __('Kontakt') }}
            </h2>
            <x-detail-grid>
                <x-detail-grid.row :label="__('Ansprechpartner')" :value="$customer->contact_name" />
                <x-detail-grid.row :label="__('E-Mail')">@if ($customer->email)<a class="link" href="mailto:{{ $customer->email }}">{{ $customer->email }}</a>@endif</x-detail-grid.row>
                <x-detail-grid.row :label="__('Telefon')" :value="$customer->phone" />
                <x-detail-grid.row :label="__('Mobil')" :value="$customer->mobile" />
                <x-detail-grid.row :label="__('Homepage')">@if ($customer->homepage)<a class="link" href="{{ $customer->homepage }}" target="_blank" rel="noopener">{{ $customer->homepage }}</a>@endif</x-detail-grid.row>
                @if ($customer->address_street || $customer->address_zip || $customer->address_city)
                    <dt class="text-base-content/60">{{ __('Adresse') }}</dt>
                    <dd class="whitespace-pre-line">{!! e($customer->address_street) !!}@if($customer->address_street)
@endif{{ trim(($customer->address_zip ?? '').' '.($customer->address_city ?? '')) }}</dd>
                @elseif ($customer->address)
                    <dt class="text-base-content/60">{{ __('Adresse') }}</dt><dd class="whitespace-pre-line">{{ $customer->address }}</dd>
                @endif
                <x-detail-grid.row :label="__('Land')" :value="$customer->country" />
            </x-detail-grid>
            @php $contactPersons = is_array($customer->contact_persons) ? array_values(array_filter($customer->contact_persons, fn($r) => is_array($r) && trim((string)($r['name'] ?? '')) !== '')) : []; @endphp
            @if ($contactPersons !== [])
                <div class="pt-3">
                    <h3 class="mb-1 text-sm font-semibold">{{ __('Ansprechpartner') }}</h3>
                    <ul class="divide-y divide-base-300 text-sm">
                        @foreach ($contactPersons as $cp)
                            <li class="py-2 flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span class="font-medium">{{ $cp['name'] ?? '' }}</span>
                                @if (! empty($cp['primary']))<x-status-badge tone="primary" size="xs">{{ __('Primär') }}</x-status-badge>@endif
                                @if (! empty($cp['email']))<a class="link link-hover" href="mailto:{{ $cp['email'] }}">{{ $cp['email'] }}</a>@endif
                                @if (! empty($cp['phone']))<span class="text-base-content/70">{{ $cp['phone'] }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-card>

        <x-card>
            <h2 class="mb-3 flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                <x-icon name="receipt_long" class="text-base-content/60" /> {{ __('Abrechnung') }}
            </h2>
            <x-detail-grid>
                <x-detail-grid.row :label="__('Abrechenbar')" :value="$customer->billable ? __('Ja') : __('Nein')" />
                <x-detail-grid.row :label="__('USt-IdNr.')" :value="$customer->vat_id" />
                <x-detail-grid.row :label="__('Währung')" :value="$customer->currency" />
                <x-detail-grid.row :label="__('Zeitzone')" :value="$customer->timezone" />
                @if ($customer->hourly_rate !== null)
                    <x-detail-grid.row :label="__('Stundensatz')" :value="number_format((float) $customer->hourly_rate, 2, ',', '.').' '.$customer->currency" />
                @endif
                @if ($customer->internal_rate !== null)
                    <x-detail-grid.row :label="__('Interner Satz')" :value="number_format((float) $customer->internal_rate, 2, ',', '.').' '.$customer->currency" />
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
    <x-card>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                <x-icon name="folder" class="text-base-content/60" /> {{ __('Projekte') }}
                <span class="font-normal text-base-content/50">({{ $projects->count() }})</span>
            </h2>
            @isset($defaultProject)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('projects.timesheets.create', $defaultProject)"
                            show-label>{{ __('Stundenzettel') }}</x-icon-btn>
            @endisset
        </div>

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
                <div role="tablist" class="tabs tabs-boxed tabs-sm">
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
    <x-card>
        <h2 class="mb-3 flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
            <x-icon name="attach_file" class="text-base-content/60" /> {{ __('Anhänge') }}
            <span class="font-normal text-base-content/50">({{ $attachments->count() }})</span>
        </h2>
        @if ($attachments->isEmpty())
            <x-empty-state compact icon='<span class="material-symbols-outlined">attach_file</span>'
                           :title="__('Keine Anhänge')"
                           :message="__('Keine Anhänge.')" />
        @else
            <ul class="divide-y divide-base-300 text-sm">
                @foreach ($attachments as $att)
                    <li class="flex items-center justify-between gap-2 py-2">
                        <div class="min-w-0 truncate">
                            <a class="link link-hover" href="{{ URL::signedRoute('attachments.download', $att) }}">{{ $att->original_name }}</a>
                            <span class="text-base-content/60">· {{ number_format($att->size / 1024, 0, ',', '.') }} KB</span>
                        </div>
                        @can('delete', $att)
                        <form method="POST" action="{{ route('attachments.destroy', $att) }}" class="inline"
                              data-confirm-dialog
                              data-confirm-message="{{ __('Anhang löschen?') }}"
                              data-confirm-icon="delete"
                              data-confirm-tone="error"
                              data-confirm-label="{{ __('Löschen') }}">
                            @csrf @method('DELETE')
                            <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                        </form>
                        @endcan
                    </li>
                @endforeach
            </ul>
        @endif
        @can('update', $customer)
        <form method="POST" action="{{ route('attachments.store', ['type' => 'customer', 'id' => $customer->sqid]) }}" enctype="multipart/form-data" class="mt-3 flex items-center gap-2">
            @csrf
            <input type="file" name="file" required class="file-input file-input-sm file-input-bordered">
            <x-icon-btn icon="upload" tone="primary" size="sm" type="submit" show-label>{{ __('Hochladen') }}</x-icon-btn>
        </form>
        @endcan
    </x-card>

    {{-- Verlauf --}}
    @if ($auditLogs->isNotEmpty())
    <x-card>
        <h2 class="mb-3 flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
            <x-icon name="history" class="text-base-content/60" /> {{ __('Verlauf') }}
        </h2>
        <ul class="divide-y divide-base-300 text-sm">
            @foreach ($auditLogs as $log)
                <li class="flex items-center justify-between gap-2 py-2">
                    <span class="flex items-center gap-2">
                        <x-status-badge tone="ghost">{{ $log->eventLabel() }}</x-status-badge>
                        {{ optional($log->user)->name ?? '—' }}
                    </span>
                    <span class="text-base-content/60">{{ $log->created_at->fdatetime() }}</span>
                </li>
            @endforeach
        </ul>
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

    @if ($lexofficePlugin && $lexofficePlugin->isEnabled() && $lexofficeVoucherCache->isNotEmpty())
        @php
            $lexofficeValueLabel = static function (?string $value, string $empty = '–'): string {
                if ($value === null || $value === '') {
                    return $empty;
                }

                $key = 'values.' . $value;
                $label = __($key);

                return $label === $key ? $value : $label;
            };
        @endphp
        <x-card class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                    <x-icon name="receipt_long" class="text-base-content/60" /> {{ __('Lexoffice-Belege') }}
                </h2>
                <span class="text-sm text-base-content/60">
                    {{ __('Summe') }}:
                    <span class="font-semibold">{{ number_format((float) $lexofficeVoucherCache->sum('total_amount'), 2, ',', '.') }}&nbsp;&euro;</span>
                </span>
            </div>
            <x-table table-sort="client">
                <x-slot:head>
                    <x-table.th sort type="string">{{ __('Nummer') }}</x-table.th>
                    <x-table.th sort type="date" default="desc">{{ __('Datum') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Typ') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Betrag') }}</x-table.th>
                </x-slot:head>
                @foreach ($lexofficeVoucherCache as $voucher)
                    <tr>
                        <td class="font-mono text-xs">{{ $voucher->voucher_number ?? '–' }}</td>
                        <td data-sort-value="{{ optional($voucher->voucher_date)->format('Y-m-d') ?? '' }}">{{ optional($voucher->voucher_date)->fdate() ?? '–' }}</td>
                        <td>{{ $lexofficeValueLabel($voucher->voucher_type) }}</td>
                        <td>
                            <x-status-badge :tone="match ($voucher->voucher_status) {
                                'paid' => 'success',
                                'paidoff' => 'success',
                                'accepted' => 'success',
                                'transferred' => 'success',
                                'open' => 'warning',
                                'sent' => 'info',
                                'overdue' => 'error',
                                'rejected' => 'error',
                                'checked' => 'success',
                                'unchecked' => 'warning',
                                'voided' => 'ghost',
                                default => 'neutral',
                            }">{{ $lexofficeValueLabel($voucher->voucher_status) }}</x-status-badge>
                        </td>
                        <td class="text-right tabular-nums" data-sort-value="{{ $voucher->total_amount }}">{{ number_format((float) $voucher->total_amount, 2, ',', '.') }}&nbsp;{{ $voucher->currency }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
</x-page-shell>
@endsection
