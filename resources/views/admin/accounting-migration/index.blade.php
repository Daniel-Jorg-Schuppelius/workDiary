{{--
  Created on   : Wed Aug 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('accounting_migration.title'))
@section('nav-title', __('accounting_migration.title'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        @if (session('success'))
            <div class="alert alert-success text-sm">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error text-sm">{{ session('error') }}</div>
        @endif

        <x-page-toolbar :subtitle="__('accounting_migration.intro')" />

        @if ($run === null)
            {{-- Planung: Datenbereiche + Stichtag --}}
            <form method="POST" action="{{ route('admin.accounting-migration.store') }}"
                  class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-3">
                @csrf
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('accounting_migration.plan_heading') }}</h2>
                <p class="text-xs text-base-content/60">{{ __('accounting_migration.plan_hint') }}</p>

                {{-- Richtung: Quelle und Ziel sind frei wählbar (MVP-653). --}}
                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="form-control">
                        <span class="label-text text-sm">{{ __('accounting_migration.source') }} *</span>
                        <select name="source" required class="select select-bordered select-sm">
                            @foreach ($providers as $provider)
                                <option value="{{ $provider->value }}" @selected($provider === \App\Enums\Migration\MigrationProvider::Lexoffice)>{{ $provider->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-control">
                        <span class="label-text text-sm">{{ __('accounting_migration.target') }} *</span>
                        <select name="target" required class="select select-bordered select-sm">
                            @foreach ($providers as $provider)
                                <option value="{{ $provider->value }}" @selected($provider === \App\Enums\Migration\MigrationProvider::OrgaMax)>{{ $provider->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <fieldset class="space-y-1">
                    <legend class="label-text text-sm">{{ __('accounting_migration.areas') }}</legend>
                    @foreach ($areas as $area)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="areas[]" value="{{ $area->value }}" class="checkbox checkbox-sm" checked>
                            {{ $area->label() }}
                            @unless ($area->isBuildable())
                                <span class="badge badge-ghost badge-xs">{{ __('accounting_migration.read_only') }}</span>
                            @endunless
                        </label>
                    @endforeach
                </fieldset>

                <label class="form-control max-w-xs">
                    <span class="label-text text-sm">{{ __('accounting_migration.cutover_on') }}</span>
                    <input type="date" name="cutover_on" class="input input-bordered input-sm" value="{{ old('cutover_on') }}">
                    <span class="label-text-alt text-xs text-base-content/50">{{ __('accounting_migration.cutover_hint') }}</span>
                </label>

                <button type="submit" class="btn btn-sm btn-primary">{{ __('accounting_migration.plan_submit') }}</button>
            </form>
        @else
            {{-- Laufender Wechsel --}}
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold">
                            {{ __('accounting_migration.run_heading', ['source' => $run->source()->label(), 'target' => $run->target()->label()]) }}
                            <span class="badge badge-{{ $run->status->tone() }} badge-sm">{{ $run->status->label() }}</span>
                        </h2>
                        <p class="text-sm text-base-content/60">
                            {{ __('accounting_migration.cutover_on') }}:
                            {{ $run->cutover_on?->fdate() ?? __('accounting_migration.no_cutover') }}
                            @if ($run->dry_run_only)
                                · <span class="badge badge-ghost badge-xs">{{ __('accounting_migration.dry_run_badge') }}</span>
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-action-form :action="route('admin.accounting-migration.analyze', $run->sqid)">
                            <button type="submit" class="btn btn-sm">{{ __('accounting_migration.analyze') }}</button>
                        </x-action-form>
                        <x-action-form :action="route('admin.accounting-migration.parallel', $run->sqid)">
                            <button type="submit" class="btn btn-sm btn-outline">{{ __('accounting_migration.start_parallel') }}</button>
                        </x-action-form>
                        <x-action-form :action="route('admin.accounting-migration.cutover', $run->sqid)"
                              :confirm="__('accounting_migration.cutover_confirm')"
                              :confirm-label="__('accounting_migration.cutover')">
                            <button type="submit" class="btn btn-sm btn-warning">{{ __('accounting_migration.cutover') }}</button>
                        </x-action-form>
                        <x-action-form :action="route('admin.accounting-migration.complete', $run->sqid)">
                            <button type="submit" class="btn btn-sm btn-success">{{ __('accounting_migration.complete') }}</button>
                        </x-action-form>
                        <a href="{{ route('admin.accounting-migration.report', $run->sqid) }}" class="btn btn-sm btn-ghost">{{ __('accounting_migration.report') }}</a>
                        <x-action-form :action="route('admin.accounting-migration.cancel', $run->sqid)"
                              :confirm="__('accounting_migration.cancel_confirm')"
                              confirm-tone="error"
                              :confirm-label="__('accounting_migration.cancel')">
                            <button type="submit" class="btn btn-sm btn-ghost text-error">{{ __('accounting_migration.cancel') }}</button>
                        </x-action-form>
                    </div>
                </div>

                @if ($run->blocked_reason)
                    <div class="alert alert-warning mt-3 text-sm">{{ $run->blocked_reason }}</div>
                @endif
            </div>

            {{-- Blocker der Umschaltung bzw. des Abschlusses --}}
            @if ($blockers !== [] || $completionBlockers !== [])
                <div class="rounded-box border border-warning/40 bg-base-100 p-4 shadow-xs">
                    <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('accounting_migration.blockers_heading') }}</h2>
                    <ul class="list-inside list-disc space-y-1 text-sm">
                        @foreach (array_unique(array_merge($blockers, $completionBlockers)) as $blocker)
                            <li>{{ $blocker }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Zählwerke je Datenbereich --}}
            @if (! empty($run->counters))
                <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                    <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('accounting_migration.counters_heading') }}</h2>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('accounting_migration.area') }}</th>
                                    <th class="text-right">{{ __('accounting_migration.counter_read') }}</th>
                                    <th class="text-right">{{ __('accounting_migration.counter_matched') }}</th>
                                    <th class="text-right">{{ __('accounting_migration.counter_pending') }}</th>
                                    <th class="text-right">{{ __('accounting_migration.counter_conflict') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($run->counters as $areaValue => $counter)
                                    <tr>
                                        <td>{{ \App\Enums\Migration\MigrationDataArea::tryFrom((string) $areaValue)?->label() ?? $areaValue }}</td>
                                        <td class="text-right tabular-nums">{{ $counter['read'] ?? 0 }}</td>
                                        <td class="text-right tabular-nums">{{ $counter['matched'] ?? ($counter['historic'] ?? 0) }}</td>
                                        <td class="text-right tabular-nums">{{ $counter['pending'] ?? ($counter['open'] ?? 0) }}</td>
                                        <td class="text-right tabular-nums">{{ $counter['conflict'] ?? 0 }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Positionen mit Entscheidung --}}
            @if ($items->isNotEmpty())
                <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                    <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('accounting_migration.items_heading') }}</h2>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('accounting_migration.area') }}</th>
                                    <th>{{ __('accounting_migration.item_title') }}</th>
                                    <th>{{ __('accounting_migration.item_source') }}</th>
                                    <th>{{ __('accounting_migration.item_target') }}</th>
                                    <th>{{ __('accounting_migration.item_status') }}</th>
                                    <th class="text-right">{{ __('accounting_migration.item_decision') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $item)
                                    <tr>
                                        <td>{{ $item->data_area->label() }}</td>
                                        <td>{{ $item->display_title }}</td>
                                        <td class="font-mono text-xs">{{ $item->source_external_id ?? '—' }}</td>
                                        <td class="font-mono text-xs">{{ $item->target_external_id ?? '—' }}</td>
                                        <td>
                                            <span class="badge badge-xs {{ $item->blocksCutover() ? 'badge-warning' : 'badge-ghost' }}">
                                                {{ __('accounting_migration.status.' . $item->status) }}
                                            </span>
                                            @if ($item->note)
                                                <div class="text-xs text-base-content/60">{{ $item->note }}</div>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <form method="POST" action="{{ route('admin.accounting-migration.decide', [$run->sqid, $item->sqid]) }}"
                                                  class="flex justify-end gap-1">
                                                @csrf
                                                <select name="status" class="select select-bordered select-xs">
                                                    <option value="matched">{{ __('accounting_migration.status.matched') }}</option>
                                                    <option value="skipped">{{ __('accounting_migration.status.skipped') }}</option>
                                                    <option value="historic">{{ __('accounting_migration.status.historic') }}</option>
                                                    <option value="conflict">{{ __('accounting_migration.status.conflict') }}</option>
                                                </select>
                                                <button type="submit" class="btn btn-xs">{{ __('Speichern') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif

        @if ($history->isNotEmpty())
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">{{ __('accounting_migration.history_heading') }}</h2>
                <ul class="space-y-1 text-sm">
                    @foreach ($history as $past)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="badge badge-{{ $past->status->tone() }} badge-xs">{{ $past->status->label() }}</span>
                            {{ $past->source()->label() }} → {{ $past->target()->label() }}
                            @if ($past->completed_at) · {{ $past->completed_at->fdatetime() }} @endif
                            <a href="{{ route('admin.accounting-migration.report', $past->sqid) }}" class="link text-xs">{{ __('accounting_migration.report') }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-page-shell>
@endsection
