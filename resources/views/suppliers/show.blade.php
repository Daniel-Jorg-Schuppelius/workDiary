@extends('layouts.app')
@section('title', $supplier->name . ' — ' . __('Lieferant'))
@section('nav-title', $supplier->name)

@section('content')
<x-page-shell>
    {{-- Header --}}
    <x-card>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="inline-block h-3 w-3 rounded-full" style="background:{{ $supplier->color ?: '#94a3b8' }}"></span>
                    <h1 class="font-['Space_Grotesk'] text-lg font-semibold truncate">{{ $supplier->name }}</h1>
                    @if ($supplier->isArchived())
                        <x-status-badge tone="ghost">{{ __('archiviert') }}</x-status-badge>
                    @endif
                    @unless ($supplier->active)
                        <x-status-badge tone="warning">{{ __('inaktiv') }}</x-status-badge>
                    @endunless
                </div>
                <p class="mt-1 text-sm text-base-content/60">
                    @if ($supplier->company){{ $supplier->company }} · @endif
                    @if ($supplier->number){{ __('Nr.') }} {{ $supplier->number }} · @endif
                    {{ $supplier->currency }}
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
                            :href="route('suppliers.index')"
                            show-label>{{ __('Zurück') }}</x-icon-btn>
                @can('update', $supplier)
                    @if ($supplier->isArchived())
                        <form method="POST" action="{{ route('suppliers.restore', $supplier) }}" class="inline">
                            @csrf
                            <x-icon-btn icon="restore" size="sm" type="submit" show-label>{{ __('Wiederherstellen') }}</x-icon-btn>
                        </form>
                    @else
                        <form method="POST" action="{{ route('suppliers.archive', $supplier) }}" class="inline">
                            @csrf
                            <x-icon-btn icon="archive" size="sm" type="submit" show-label>{{ __('Archivieren') }}</x-icon-btn>
                        </form>
                    @endif
                    <x-icon-btn icon="edit" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('suppliers.edit', $supplier)"
                                show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                @endcan
            </div>
        </div>
    </x-card>

    {{-- Stammdaten --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-card>
            <h2 class="mb-3 flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                <x-icon name="contacts" class="text-base-content/60" /> {{ __('Kontakt') }}
            </h2>
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
            @php $contactPersons = is_array($supplier->contact_persons) ? array_values(array_filter($supplier->contact_persons, fn($r) => is_array($r) && trim((string)($r['name'] ?? '')) !== '')) : []; @endphp
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
                <x-icon name="store" class="text-base-content/60" /> {{ __('Geschäftsdaten') }}
            </h2>
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
        @can('update', $supplier)
        <form method="POST" action="{{ route('attachments.store', ['type' => 'supplier', 'id' => $supplier->sqid]) }}" enctype="multipart/form-data" class="mt-3 flex items-center gap-2">
            @csrf
            <input type="file" name="file" required class="file-input file-input-sm file-input-bordered">
            <x-icon-btn icon="upload" tone="primary" size="sm" type="submit" show-label>{{ __('Hochladen') }}</x-icon-btn>
        </form>
        @endcan
    </x-card>

    {{-- Lexoffice-Belege --}}
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
            <x-table>
                <x-slot:head>
                    <th>{{ __('Nummer') }}</th>
                    <th>{{ __('Datum') }}</th>
                    <th>{{ __('Typ') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Betrag') }}</th>
                </x-slot:head>
                @foreach ($lexofficeVoucherCache as $voucher)
                    <tr>
                        <td class="font-mono text-xs">{{ $voucher->voucher_number ?? '–' }}</td>
                        <td>{{ optional($voucher->voucher_date)->format('d.m.Y') ?? '–' }}</td>
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
                        <td class="text-right tabular-nums">{{ number_format((float) $voucher->total_amount, 2, ',', '.') }}&nbsp;{{ $voucher->currency }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif

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
                    <span class="text-base-content/60">{{ $log->created_at->format('d.m.Y H:i') }}</span>
                </li>
            @endforeach
        </ul>
    </x-card>
    @endif
</x-page-shell>
@endsection
