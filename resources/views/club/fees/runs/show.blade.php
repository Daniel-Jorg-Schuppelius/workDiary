{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Beitragslauf (MVP-850): eingefrorene Vorschau je Konto, Fehler, Freigabe/Neuberechnung/Übergabe, erzeugte Forderungen. --}}
@extends('layouts.app')
@section('title', __('club.fees.title.run', ['month' => $run->monthLabel()]))
@section('nav-title', __('club.fees.title.runs'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('club.fees.title.run', ['month' => $run->monthLabel()])" :badge="$run->status->label()" :badgeTone="$run->status->tone()">
            <x-slot:actions>
                @if ($canManage && $run->isDraft())
                    @unless ($external || $run->hasIssues())
                        <x-action-form :action="route('club.fees.runs.release', $run)" :confirm="__('club.fees.confirm.release', ['count' => $positions->pluck('account_id')->unique()->count()])" confirm-icon="verified" confirm-tone="primary">
                            <x-icon-btn type="submit" icon="verified" tone="primary" size="sm" show-label>{{ __('club.fees.action.release') }}</x-icon-btn>
                        </x-action-form>
                    @endunless
                    <x-action-form :action="route('club.fees.runs.recalculate', $run)">
                        <x-icon-btn type="submit" icon="refresh" tone="outline" size="sm" show-label>{{ __('club.fees.action.recalculate') }}</x-icon-btn>
                    </x-action-form>
                    <x-action-form :action="route('club.fees.runs.cancel', $run)" :confirm="__('club.fees.confirm.cancel_run')" confirm-icon="close" confirm-tone="warning">
                        <x-icon-btn type="submit" icon="close" tone="outline" size="sm" class="btn-warning" show-label>{{ __('club.fees.action.cancel_run') }}</x-icon-btn>
                    </x-action-form>
                @endif
                <x-icon-btn icon="download" tone="outline" size="sm" :href="route('club.fees.runs.export', $run)" show-label>{{ __('club.fees.action.export') }}</x-icon-btn>
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('club.fees.runs.index')" show-label>{{ __('club.action.back') }}</x-icon-btn>
                <x-help-button topic="club.fees" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($errors->any())
        <div class="alert alert-error text-sm" role="alert">
            <x-icon name="error" />
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if ($external)
        <div class="alert alert-warning text-sm" role="status"><x-icon name="lock" /><span>{{ __('club.fees.hint.external_billing', ['mode' => $external->label()]) }}</span></div>
    @endif
    @if ($run->hasIssues())
        <div class="alert alert-error text-sm" role="alert">
            <x-icon name="error" />
            <div>
                <p class="font-medium">{{ __('club.fees.hint.run_issues') }}</p>
                <ul class="list-inside list-disc">
                    @foreach ($run->issues as $issue)
                        <li>{{ $issue['message'] ?? '' }}@if (! empty($issue['member_id'])) — {{ $members->get((int) $issue['member_id'])?->fullName() ?? $issue['member_id'] }}@endif</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('club.fees.card.positions')" icon="calculate" :count="$positions->count()">
                <p class="mb-2 text-xs text-muted">{{ __('club.fees.hint.frozen', ['at' => $run->calculated_at?->orgTz()->format('d.m.Y H:i') ?? '–']) }}</p>
                <x-table :bare="true" size="sm">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('club.fees.field.account') }}</th>
                            <th>{{ __('club.field.member') }}</th>
                            <th>{{ __('club.fees.field.position') }}</th>
                            <th>{{ __('club.field.period') }}</th>
                            <th class="text-right">{{ __('club.fees.field.amount') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($positions->groupBy('account_id') as $accountId => $rows)
                        @foreach ($rows as $index => $p)
                            <tr class="{{ $index === 0 ? 'border-t-2 border-base-300' : '' }}">
                                <td class="text-sm font-medium">@if ($index === 0){{ $accounts->get((int) $accountId)?->name ?? '–' }}@endif</td>
                                <td class="text-sm">{{ $p['member_id'] ? ($members->get((int) $p['member_id'])?->fullName() ?? '–') : __('club.fees.label.whole_account') }}</td>
                                <td class="text-sm">{{ $p['label'] }} <span class="text-xs text-muted">({{ \App\Enums\Club\ClubFeePositionKind::tryFrom($p['kind'])?->label() ?? $p['kind'] }})</span></td>
                                <td class="whitespace-nowrap text-sm tabular-nums">{{ \Carbon\CarbonImmutable::parse($p['period_start'])->format('d.m.Y') }} – {{ \Carbon\CarbonImmutable::parse($p['period_end'])->format('d.m.Y') }}</td>
                                <td class="text-right tabular-nums">{{ \CommonToolkit\ValueObjects\Money::of($p['amount'], \CommonToolkit\Enums\CurrencyCode::tryFrom($p['currency']) ?? \CommonToolkit\Enums\CurrencyCode::Euro)->format() }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <x-table.empty icon="calculate" :colspan="5" :title="__('club.fees.empty.positions')" compact />
                    @endforelse
                    @if ($positions->isNotEmpty())
                        <x-slot:foot>
                            <tr><th colspan="4" class="text-right">{{ __('club.fees.label.total') }}</th><th class="text-right tabular-nums">{{ $run->total->format() }}</th></tr>
                        </x-slot:foot>
                    @endif
                </x-table>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card :title="__('club.fees.card.claims')" icon="receipt_long" :count="$claims->count()">
                <ul class="space-y-1 text-sm">
                    @forelse ($claims as $claim)
                        <li class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('club.fees.claims.show', $claim) }}" class="link link-hover font-medium">{{ $claim->number }}</a>
                            <span class="text-xs text-muted">{{ $claim->account?->name }}</span>
                            <span class="ml-auto tabular-nums">{{ $claim->total->format() }}</span>
                            <x-status-badge :tone="$claim->status->tone()" size="xs">{{ $claim->status->label() }}</x-status-badge>
                        </li>
                    @empty
                        <li class="text-muted">{{ $run->isDraft() ? __('club.fees.label.not_released') : __('club.fees.empty.claims') }}</li>
                    @endforelse
                </ul>
                @if ($run->released_at)
                    <p class="mt-2 text-xs text-muted">{{ __('club.fees.label.released_at', ['at' => $run->released_at->orgTz()->format('d.m.Y H:i'), 'by' => $run->releasedBy?->name ?? '–']) }}</p>
                @endif
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection
