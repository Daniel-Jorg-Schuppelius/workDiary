{{--
    Lexoffice-Belege (Rechnungen/Aufträge/Angebote/Lieferscheine …) eines
    verknüpften Kontakts — Kunde ODER Lieferant. Auf den globalen Header-Zeitraum
    eingegrenzt.

    Erwartete Variablen:
      $plugin      — Lexoffice-Plugin-Instanz (oder null)
      $contactRef  — ExternalReference des verknüpften Lexoffice-Kontakts (oder null)
      $vouchers    — Collection<LexofficeVoucher> (bereits zeitraumgefiltert)
      $range       — array{label: string, ...} aus globalDateRange()
--}}
@php
    $valueLabel = static function (?string $value, string $empty = '–'): string {
        if ($value === null || $value === '') {
            return $empty;
        }
        $key = 'values.' . $value;
        $label = __($key);

        return $label === $key ? $value : $label;
    };
    $byType = $vouchers->groupBy('voucher_type');
@endphp

@if ($plugin && $plugin->isEnabled() && $contactRef)
    <x-card class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                <x-icon name="receipt_long" class="text-base-content/60" /> {{ __('Lexoffice-Belege') }}
                <span class="badge badge-ghost badge-sm">{{ $range['label'] }}</span>
            </h2>
            <div class="flex items-center gap-3">
                <span class="text-sm text-base-content/60">
                    {{ __('Summe') }}:
                    <span class="font-semibold">{{ number_format((float) $vouchers->sum('total_amount'), 2, ',', '.') }}&nbsp;&euro;</span>
                </span>
                @isset($syncRoute)
                    <form method="POST" action="{{ $syncRoute }}">
                        @csrf
                        <x-icon-btn icon="sync" size="xs" tone="ghost" type="submit" :title="__('Belege synchronisieren')" />
                    </form>
                @endisset
            </div>
        </div>

        @if ($byType->isNotEmpty())
            <div class="flex flex-wrap gap-1.5">
                @foreach ($byType as $type => $group)
                    <span class="badge badge-sm badge-outline">{{ $valueLabel($type) }}: {{ $group->count() }}</span>
                @endforeach
            </div>
        @endif

        @if ($vouchers->isEmpty())
            <p class="text-sm text-base-content/60">{{ __('Keine Belege im gewählten Zeitraum.') }}</p>
        @else
            <x-table table-sort="client">
                <x-slot:head>
                    <x-table.th sort type="string">{{ __('Nummer') }}</x-table.th>
                    <x-table.th sort type="date" default="desc">{{ __('Datum') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Typ') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('Betrag') }}</x-table.th>
                    <x-table.th align="right"></x-table.th>
                </x-slot:head>
                @foreach ($vouchers as $voucher)
                    <tr>
                        <td class="font-mono text-xs">{{ $voucher->voucher_number ?? '–' }}</td>
                        <td data-sort-value="{{ optional($voucher->voucher_date)->format('Y-m-d') ?? '' }}">{{ optional($voucher->voucher_date)->fdate() ?? '–' }}</td>
                        <td>{{ $valueLabel($voucher->voucher_type) }}</td>
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
                            }">{{ $valueLabel($voucher->voucher_status) }}</x-status-badge>
                        </td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (float) $voucher->total_amount }}">{{ number_format((float) $voucher->total_amount, 2, ',', '.') }}&nbsp;{{ $voucher->currency }}</td>
                        <td class="text-right">
                            <div class="flex justify-end gap-1">
                                @if (in_array($voucher->voucher_type, ['invoice', 'salesinvoice'], true) && $voucher->voucher_status === 'overdue')
                                    <form method="POST" action="{{ route('lexoffice.vouchers.dunning', $voucher) }}">
                                        @csrf
                                        <x-icon-btn icon="notification_important" size="xs" tone="warning" type="submit" :title="__('Mahnung erstellen')" />
                                    </form>
                                @endif
                                <x-icon-btn icon="visibility" size="xs"
                                            :href="route('lexoffice.vouchers.preview', $voucher)"
                                            data-entry-modal-trigger
                                            :label="__('Belegbild anzeigen')" />
                                <x-icon-btn icon="download" size="xs"
                                            :href="route('lexoffice.vouchers.file', [$voucher, 'download' => 1])"
                                            :label="__('Belegbild herunterladen')" />
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
@endif
