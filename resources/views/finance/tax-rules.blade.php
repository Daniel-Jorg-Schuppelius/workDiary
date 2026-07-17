@extends('layouts.app')

@section('title', __('Steuerregeln'))
@section('nav-title', __('Steuerregelmatrix'))

@section('content')
<x-page-shell>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <x-page-toolbar :title="__('Steuerregelmatrix')">
        <div class="text-sm text-base-content/70">{{ __('Versionierter Katalog mit Stichtags-Auflösung — Gesetzesänderungen sind Datenpflege, kein Release. Keine Steuerberatung.') }}</div>
    </x-page-toolbar>

    @foreach ($gaps as $gap)
        <div class="alert alert-warning text-sm">
            <span class="material-symbols-outlined" aria-hidden="true">warning</span>
            {{ __('Lückenwarnung: :gap', ['gap' => $gap]) }}
        </div>
    @endforeach

    <x-card :title="__('Org-Regel anlegen (Override des Katalogs)')">
        <form method="POST" action="{{ route('finance.tax-rules.store') }}" class="grid gap-2 sm:grid-cols-4">
            @csrf
            <input name="country" required maxlength="2" class="input input-sm input-bordered uppercase" placeholder="DE" value="DE">
            <select name="category" class="select select-sm select-bordered">
                @foreach (\App\Models\TaxRule::CATEGORIES as $category)
                    <option value="{{ $category }}">{{ __("values.$category") }}</option>
                @endforeach
            </select>
            <select name="rate_type" class="select select-sm select-bordered">
                @foreach (\App\Models\TaxRule::RATE_TYPES as $type)
                    <option value="{{ $type }}">{{ __("values.$type") }}</option>
                @endforeach
            </select>
            <input name="rate" type="number" step="0.01" min="0" max="99.99" required class="input input-sm input-bordered" placeholder="%">
            <x-date-range :label="false"
                          from-name="valid_from" to-name="valid_to" from-required
                          :from-label="__('gültig ab')" :to-label="__('gültig bis')" />
            <input name="source" maxlength="300" class="input input-sm input-bordered" placeholder="{{ __('Quelle/Fundstelle') }}">
            <input name="note" maxlength="500" class="input input-sm input-bordered" placeholder="{{ __('Beleg-Hinweistext') }}">
            <div class="sm:col-span-4"><x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('Regel anlegen (mit Überschneidungsprüfung)') }}</x-icon-btn></div>
        </form>

        <form method="POST" action="{{ route('finance.tax-rules.import') }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-2">
            @csrf
            <input type="file" name="file" required accept=".csv,.txt" class="file-input file-input-sm file-input-bordered">
            <x-icon-btn icon="upload" size="sm" type="submit" show-label>{{ __('CSV-Import') }}</x-icon-btn>
            <span class="text-xs text-base-content/60">{{ __('Format: country;category;rate_type;rate;valid_from;valid_to;source;note') }}</span>
        </form>
    </x-card>

    <x-card padding="p-0" :title="__('Regeln (Katalog + Org-Overrides)')">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Land') }}</th>
                        <th>{{ __('Kategorie') }}</th>
                        <th>{{ __('Satztyp') }}</th>
                        <th class="text-right">{{ __('Satz %') }}</th>
                        <th>{{ __('Gültig') }}</th>
                        <th>{{ __('Quelle') }}</th>
                        <th>{{ __('Herkunft') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rules as $rule)
                        <tr @class(['opacity-50' => $rule->status !== 'active'])>
                            <td>{{ $rule->country }}@if ($rule->region)/{{ $rule->region }}@endif</td>
                            <td>{{ __("values.{$rule->category}") }}</td>
                            <td>{{ __("values.{$rule->rate_type}") }}</td>
                            <td class="text-right tabular-nums">{{ rtrim(rtrim((string) $rule->rate, '0'), '.') }}</td>
                            <td>{{ $rule->valid_from->fdate() }} – {{ optional($rule->valid_to)->fdate() ?? '∞' }}</td>
                            <td class="max-w-xs truncate text-xs text-base-content/70" title="{{ $rule->source }}">{{ $rule->source ?? '—' }}</td>
                            <td>{{ $rule->organization_id !== null ? __('Org-Override') : __('Katalog') }}</td>
                            <td><x-status-badge size="xs" outline>{{ __("values.{$rule->status}") }}</x-status-badge></td>
                            <td>
                                @if ($rule->organization_id !== null && $rule->status === 'active')
                                    <x-action-form :action="route('finance.tax-rules.retire', $rule)"
                                          :confirm="__('Regel stilllegen (Rollback)? Ältere Regeln/Katalog greifen wieder.')"
                                          confirm-icon="history" confirm-tone="warning" :confirm-label="__('Stilllegen')">
                                        <x-icon-btn icon="history" size="xs" tone="warning" type="submit" :title="__('Stilllegen (Rollback)')" />
                                    </x-action-form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</x-page-shell>
@endsection
