{{--
  Created on   : Thu Jul 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _billing_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Kundenakte-Panel „Sonderkonditionen & Abrechnungskonto" (Feature 098).
     Erwartet: $customer, $billingAgreement, $billingStatements,
     $billingPayments, $billingStrayEntries, $billingActivityCategories.
     Nur mit update-Recht eingebunden (CustomerController::show). --}}

@php
    $money = fn ($v) => $v === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v instanceof \CommonToolkit\ValueObjects\Money ? $v->toFloat() : (float) $v, 2, withThousandsSeparator: true) . ' €';
    $hours = fn (int $m): string => \App\Support\Formats::duration($m);
@endphp

<x-card :title="__('customer-billing.panel_title')" icon="request_quote" id="customer-billing" padding="p-0">
    <x-slot:actions>
        @if ($billingAgreement?->isAccountMode())
            <x-icon-btn icon="payments" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('customers.billing.payments.create', $customer)"
                        show-label>{{ __('customer-billing.book_payment') }}</x-icon-btn>
        @endif
        @if ($billingAgreement?->isRetainerMode())
            <x-action-form :action="route('customers.billing.retainer.trueup', $customer)"
                           :confirm="__('customer-billing.confirm_trueup')"
                           confirm-icon="request_quote" :confirm-label="__('customer-billing.trueup')">
                <x-icon-btn icon="request_quote" tone="primary" size="sm" type="submit" show-label>{{ __('customer-billing.trueup') }}</x-icon-btn>
            </x-action-form>
        @endif
        <x-icon-btn :icon="$billingAgreement ? 'edit' : 'add'" tone="ghost" size="sm"
                    data-entry-modal-trigger
                    :href="route('customers.billing.agreement.edit', $customer)"
                    show-label>{{ $billingAgreement ? __('customer-billing.edit_agreement') : __('customer-billing.create_agreement') }}</x-icon-btn>
    </x-slot:actions>

    @if ($billingAgreement === null)
        <p class="px-4 py-6 text-sm text-base-content/60">
            {{ __('customer-billing.no_agreement_hint') }}
        </p>
    @else
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 border-b border-base-300 px-4 py-3 text-sm">
            <span class="flex items-center gap-2">
                <x-status-badge :tone="$billingAgreement->mode->tone()">{{ $billingAgreement->mode->label() }}</x-status-badge>
                @unless ($billingAgreement->active)
                    <x-status-badge tone="warning">{{ __('Inaktiv') }}</x-status-badge>
                @endunless
            </span>
            <span class="text-base-content/70">
                {{ __('customer-billing.workdays_short', ['count' => $billingAgreement->workdays_per_week]) }}
                @if ($billingAgreement->holidays_as_weekend)
                    · {{ __('customer-billing.holidays_as_weekend_short') }}
                @endif
            </span>
            @if ($billingAgreement->travel_minutes_per_entry > 0)
                <span class="text-base-content/70">
                    {{ __('customer-billing.travel_short', ['minutes' => $billingAgreement->travel_minutes_per_entry]) }}
                </span>
            @endif
            @if ($billingAgreement->expected_monthly_amount !== null)
                <span class="text-base-content/70">{{ __('customer-billing.expected_monthly') }}: {{ $money($billingAgreement->expected_monthly_amount) }}</span>
            @endif
            <span class="flex flex-wrap gap-1">
                @forelse ($billingAgreement->rates as $rate)
                    <span class="badge badge-ghost badge-sm tabular-nums">
                        {{ $rate->activityCategory?->label ?? __('customer-billing.all_categories') }}
                        · {{ $rate->day_type->label() }}
                        · {{ $money($rate->hourly_rate) }}
                    </span>
                @empty
                    <span class="text-warning">{{ __('customer-billing.no_rates_hint') }}</span>
                @endforelse
            </span>
        </div>

        @if ($billingAgreement->isRetainerMode())
            <div class="mx-4 mt-3 alert text-sm">
                <x-icon name="info" />
                <span>{{ __('customer-billing.retainer_hint') }}</span>
            </div>
        @endif

        @if ($billingAgreement->keepsLedger())
            @if ($billingStrayEntries !== [])
                <div class="mx-4 mt-3 alert alert-warning text-sm">
                    <x-icon name="warning" />
                    <span>
                        {{ trans_choice('customer-billing.stray_warning', count($billingStrayEntries), ['count' => count($billingStrayEntries)]) }}
                        ({{ collect($billingStrayEntries)->pluck('date')->unique()->sort()->implode(', ') }})
                    </span>
                </div>
            @endif

            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('customer-billing.month') }}</th>
                        <th class="text-right">{{ __('customer-billing.hours') }}</th>
                        <th class="text-right">{{ __('customer-billing.gross_value') }}</th>
                        <th class="text-right">{{ __('customer-billing.payments_total') }}</th>
                        <th class="text-right">{{ __('customer-billing.carry_in') }}</th>
                        <th class="text-right">{{ __('customer-billing.balance') }}</th>
                        @if ($billingAgreement->isRetainerMode())
                            <th>{{ __('customer-billing.retainer_invoice') }}</th>
                        @endif
                        <th></th>
                        <th class="text-right">{{ __('Aktionen') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($billingStatements as $statement)
                    <tr>
                        <td class="whitespace-nowrap">
                            <a href="{{ route('customers.billing.statements.show', [$customer, $statement]) }}" class="link link-hover">{{ $statement->periodLabel() }}</a>
                        </td>
                        <td class="text-right tabular-nums">
                            {{ $hours($statement->total_minutes) }}
                            @if ($statement->travel_minutes > 0)
                                <span class="text-xs text-base-content/60">+{{ $hours($statement->travel_minutes) }}</span>
                            @endif
                        </td>
                        <td class="text-right tabular-nums">{{ $money($statement->gross_value) }}</td>
                        <td class="text-right tabular-nums">{{ $money($statement->payments_total) }}</td>
                        <td class="text-right tabular-nums">{{ $money($statement->carry_in) }}</td>
                        <td class="text-right tabular-nums font-medium">{{ $money($statement->balance) }}</td>
                        @if ($billingAgreement->isRetainerMode())
                            <td class="text-sm">
                                @if ($statement->retainerInvoice)
                                    <span class="tabular-nums">{{ $statement->retainerInvoice->number }}</span>
                                    <x-status-badge :tone="$statement->retainerInvoice->status === \App\Models\Invoice::STATUS_PAID ? 'success' : 'ghost'">
                                        {{ $statement->retainerInvoice->status }}
                                    </x-status-badge>
                                @elseif ($statement->lexofficeVoucher)
                                    {{-- Direkt in Lexoffice geführter Beleg (verknüpft, nicht gepusht). --}}
                                    <span class="tabular-nums">{{ $statement->lexofficeVoucher->voucher_number ?? '—' }}</span>
                                    <x-status-badge :tone="$statement->lexofficeVoucher->open_amount?->isPositive() ? 'ghost' : 'success'">
                                        {{ $money($statement->lexofficeVoucher->net_amount ?? $statement->lexofficeVoucher->total_amount) }}
                                    </x-status-badge>
                                @else
                                    <span class="text-base-content/40">—</span>
                                @endif
                            </td>
                        @endif
                        <td>
                            @if ($statement->locked)
                                <x-status-badge tone="success">{{ __('customer-billing.locked') }}</x-status-badge>
                            @else
                                <x-status-badge tone="ghost">{{ __('customer-billing.open') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                @if ($billingAgreement->isRetainerMode() && ! $statement->hasRetainerCharge() && ! $statement->locked)
                                    {{-- Senden nur, solange kein Beleg hängt — sonst entstünde in
                                         Lexoffice eine zweite Rechnung für denselben Monat. --}}
                                    <x-action-form :action="route('customers.billing.retainer.push', $customer)"
                                                   :confirm="__('customer-billing.confirm_retainer_push', ['period' => $statement->periodLabel()])"
                                                   confirm-icon="send" :confirm-label="__('customer-billing.send_retainer')">
                                        <input type="hidden" name="year" value="{{ $statement->year }}">
                                        <input type="hidden" name="month" value="{{ $statement->month }}">
                                        <x-icon-btn icon="send" type="submit" :label="__('customer-billing.send_retainer')" />
                                    </x-action-form>
                                    <x-icon-btn icon="link" data-entry-modal-trigger
                                                :href="route('customers.billing.retainer.voucher.edit', [$customer, $statement])"
                                                :label="__('customer-billing.link_voucher')" />
                                @elseif ($billingAgreement->isRetainerMode() && $statement->lexoffice_voucher_id !== null && ! $statement->locked)
                                    <x-action-form :action="route('customers.billing.retainer.voucher.unlink', [$customer, $statement])" method="DELETE"
                                                   :confirm="__('customer-billing.confirm_unlink_voucher', ['period' => $statement->periodLabel()])"
                                                   confirm-icon="link_off" confirm-tone="error" :confirm-label="__('customer-billing.unlink_voucher')">
                                        <x-icon-btn icon="link_off" type="submit" :label="__('customer-billing.unlink_voucher')" />
                                    </x-action-form>
                                @endif
                                @if ($statement->locked)
                                    <x-action-form :action="route('customers.billing.statements.reopen', [$customer, $statement])"
                                                   :confirm="__('customer-billing.confirm_reopen', ['period' => $statement->periodLabel()])"
                                                   confirm-icon="lock_open" :confirm-label="__('customer-billing.reopen')">
                                        <x-icon-btn icon="lock_open" type="submit" :label="__('customer-billing.reopen')" />
                                    </x-action-form>
                                @else
                                    <x-action-form :action="route('customers.billing.statements.close', [$customer, $statement])"
                                                   :confirm="__('customer-billing.confirm_close', ['period' => $statement->periodLabel()])"
                                                   confirm-icon="lock" :confirm-label="__('customer-billing.close')">
                                        <x-icon-btn icon="lock" type="submit" :label="__('customer-billing.close')" />
                                    </x-action-form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="$billingAgreement->isRetainerMode() ? 9 : 8" :title="__('customer-billing.no_statements')" compact />
                @endforelse
            </x-table>

            @if ($billingPayments->isNotEmpty())
                <div class="border-t border-base-300 px-4 py-2 text-sm font-medium">{{ __('customer-billing.recent_payments') }}</div>
                <x-table bare>
                    @foreach ($billingPayments as $payment)
                        <tr>
                            <td class="whitespace-nowrap">{{ $payment->paid_on->fdate() }}</td>
                            <td class="text-right tabular-nums">{{ $money($payment->amount) }}</td>
                            <td><x-status-badge :tone="$payment->source->tone()">{{ $payment->source->label() }}</x-status-badge></td>
                            <td class="max-w-[16rem] truncate text-base-content/70">{{ $payment->note ?? '—' }}</td>
                            <td class="text-right">
                                @if (! in_array($payment->source, [\App\Enums\Billing\AccountPaymentSource::Bank, \App\Enums\Billing\AccountPaymentSource::Lexoffice], true))
                                    <x-action-form :action="route('customers.billing.payments.destroy', [$customer, $payment])" method="DELETE"
                                                   :confirm="__('customer-billing.confirm_void_payment')"
                                                   confirm-icon="delete" confirm-tone="error" :confirm-label="__('customer-billing.void_payment')">
                                        <x-icon-btn icon="delete" type="submit" tone="error" :label="__('customer-billing.void_payment')" />
                                    </x-action-form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            @endif

            <div class="flex justify-end border-t border-base-300 px-4 py-2">
                <x-action-form :action="route('customers.billing.recalculate', $customer)">
                    <x-icon-btn icon="refresh" type="submit" size="sm" show-label>{{ __('customer-billing.recalculate') }}</x-icon-btn>
                </x-action-form>
            </div>
        @else
            <p class="px-4 py-4 text-sm text-base-content/60">
                {{ __('customer-billing.invoice_mode_hint') }}
            </p>
        @endif
    @endif
</x-card>
