{{-- Prüffälle an Bezügen (überbucht, zeitlich unpassend, anderer Artikel, anderer Betrag) als Tabellenzeilen: Lizenzkonto und Arbeitsmappe (MVP-1749, MVP-1769). --}}
@php $compact = \App\View\Components\Resale\LicenceMonths::class; @endphp
@foreach ($findings as $finding)
    @php
        $line = $finding['line'];
        $isDeviation = in_array($finding['kind'], \App\Services\Reselling\Register\LinkAudit::DEVIATIONS, true);
    @endphp
    <tr class="hover">
        {{-- Auswahl für „Ausgewählte lösen“ (MVP-1784): nur Prüffälle mit genau einem Bezug. --}}
        <td class="w-8 align-top">
            @if ($canManage && $finding['link'] !== null)
                <input type="checkbox" class="checkbox checkbox-sm" data-bulk-checkbox name="ids[]" value="{{ $finding['link']->sqid }}" aria-label="{{ __('Auswählen') }}">
            @endif
        </td>
        {{-- nowrap: daisyUI-Badges haben feste Höhe, ein umbrechendes Label läuft über den Hintergrund. --}}
        <td class="align-top whitespace-nowrap"><x-status-badge size="xs" :tone="$finding['kind'] === \App\Services\Reselling\Register\LinkAudit::OVERBOOKED ? 'error' : 'warning'" :label="__('resale.account.finding.' . $finding['kind'])" /></td>
        <td class="text-sm">
            <span class="font-mono text-xs">{{ $line->voucherNumber }}</span> · {{ __('resale.link_ui.position', ['position' => $line->position]) }} · {{ \Illuminate\Support\Str::limit($line->label(), 40) }}
            @if ($finding['kind'] === \App\Services\Reselling\Register\LinkAudit::OVERBOOKED)
                <span class="block text-xs text-muted">{{ __('resale.account.finding.overbooked_text', ['used' => $compact::compact(\App\Services\Reselling\Register\LicenseMonths::ofLine($line) + $finding['excess']), 'available' => $compact::compact(\App\Services\Reselling\Register\LicenseMonths::ofLine($line))]) }}</span>
                <ul class="mt-1 space-y-0.5 text-xs">
                    @foreach ($finding['links'] as $link)
                        <li class="flex items-center justify-between gap-2">
                            <span>{{ \App\Services\Reselling\Register\PeriodLinker::targetLabel($link) }} · {{ $compact::compact((float) $link->months) }} {{ __('resale.link.months') }}</span>
                            @if ($canManage)
                                <form method="POST" action="{{ route('finance.resale.links.destroy', $link->sqid) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-btn icon="link_off" size="xs" tone="ghost" type="submit" :title="__('resale.link.action.unlink')" />
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @elseif ($isDeviation)
                @php
                    $currency = $finding['period']->currency;
                    $money = static fn(float $value): string => \CommonToolkit\ValueObjects\Money::ofFloat($value, $currency, 2)->format();
                @endphp
                <span class="block text-xs text-muted">{{ __('resale.account.finding.' . $finding['kind'] . '_text', [
                    'period' => $finding['period']->subscription->label . ' · ' . $finding['period']->label(),
                    'diff' => $money(abs($finding['diff'])),
                    'direction' => __('resale.deviation.' . ($finding['diff'] >= 0 ? 'under' : 'over')),
                ]) }}</span>
            @else
                <span class="block text-xs text-muted">{{ __('resale.account.finding.mismatch_text', ['service' => $line->servicePeriodLabel() ?? $line->voucherDate?->fdate() ?? '—', 'period' => $finding['period']->label()]) }}</span>
            @endif
        </td>
        <td class="text-right align-top">
            @if ($canManage && $isDeviation)
                {{-- Abweichung bleibt möglich, aber nur begründet (MVP-1768): danach zählt sie nicht mehr als Prüffall. --}}
                <div class="flex justify-end items-center gap-1">
                    <form method="POST" action="{{ route('finance.resale.periods.deviation.store', $finding['period']->sqid) }}" class="flex items-center gap-1">
                        @csrf
                        <input type="text" name="deviation_reason" required maxlength="255" class="input input-xs input-bordered w-48"
                               placeholder="{{ __('resale.deviation.reason_field') }}" aria-label="{{ __('resale.deviation.reason_field') }}">
                        <x-icon-btn icon="task_alt" size="xs" tone="primary" type="submit" show-label>{{ __('resale.deviation.accept') }}</x-icon-btn>
                    </form>
                    @if ($finding['kind'] === \App\Services\Reselling\Register\LinkAudit::ARTICLE)
                        <form method="POST" action="{{ route('finance.resale.links.destroy', $finding['link']->sqid) }}">
                            @csrf
                            @method('DELETE')
                            <x-icon-btn icon="link_off" size="xs" tone="ghost" type="submit" :title="__('resale.link.action.unlink')" />
                        </form>
                    @endif
                </div>
            @endif
            @if ($canManage && $finding['kind'] === \App\Services\Reselling\Register\LinkAudit::MISMATCH)
                <div class="flex justify-end gap-1">
                    @if ($finding['suggested'] !== null)
                        <form method="POST" action="{{ route('finance.resale.links.move', $finding['link']->sqid) }}">
                            @csrf
                            <input type="hidden" name="period_id" value="{{ $finding['suggested']->sqid }}">
                            <x-icon-btn icon="move_down" size="xs" tone="primary" type="submit" show-label>{{ __('resale.account.finding.move', ['period' => $finding['suggested']->label()]) }}</x-icon-btn>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('finance.resale.links.destroy', $finding['link']->sqid) }}">
                        @csrf
                        @method('DELETE')
                        <x-icon-btn icon="link_off" size="xs" tone="ghost" type="submit" :title="__('resale.link.action.unlink')" />
                    </form>
                </div>
            @endif
        </td>
    </tr>
@endforeach
