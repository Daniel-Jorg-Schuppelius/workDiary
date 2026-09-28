{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : compare.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Abschnittsvergleich zweier Fassungen (MVP-995). Variablen: $old, $new, $rows, $document
--}}
@extends('layouts.app')
@section('title', __('procedure-documentation.compare.title', ['old' => $old->displayVersion(), 'new' => $new->displayVersion()]))
@section('nav-title', __('procedure-documentation.compare.title', ['old' => $old->displayVersion(), 'new' => $new->displayVersion()]))
@section('content')
@php
    $tones = ['unchanged' => 'ghost', 'changed' => 'warning', 'added' => 'success', 'removed' => 'error'];
    $changed = collect($rows)->where('status', '!=', 'unchanged');
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('procedure-documentation.compare.subtitle', ['count' => $changed->count()])"
                        back-route="finance.procedure-documentation.show" :back-params="['document' => $document]" :back-label="__('procedure-documentation.action.back')" />
    </x-slot:toolbar>

    <x-card>
        <x-table :bare="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('procedure-documentation.compare.section') }}</th>
                    <th>{{ __('procedure-documentation.field.status') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($rows as $row)
                <tr class="hover">
                    <td class="text-sm">
                        <span class="text-xs text-muted">{{ __('procedure-documentation.compare.part.' . $row['part']) }}</span>
                        <a class="link link-hover block font-medium" href="#cmp-{{ $row['part'] }}-{{ $row['key'] }}">{{ $row['title'] }}</a>
                    </td>
                    <td><x-status-badge :tone="$tones[$row['status']]" size="sm">{{ __('procedure-documentation.compare.status.' . $row['status']) }}</x-status-badge></td>
                </tr>
            @endforeach
        </x-table>
    </x-card>

    @foreach ($changed as $row)
        <x-card :title="$row['title']" icon="compare_arrows" id="cmp-{{ $row['part'] }}-{{ $row['key'] }}">
            <div class="grid gap-3 md:grid-cols-2">
                <div>
                    <p class="mb-1 text-xs font-semibold text-muted">{{ $old->displayVersion() }}</p>
                    <pre class="whitespace-pre-wrap rounded-box bg-base-200 p-3 text-xs">{{ $row['old'] ?? '—' }}</pre>
                </div>
                <div>
                    <p class="mb-1 text-xs font-semibold text-muted">{{ $new->displayVersion() }}</p>
                    <pre class="whitespace-pre-wrap rounded-box bg-base-200 p-3 text-xs">{{ $row['new'] ?? '—' }}</pre>
                </div>
            </div>
        </x-card>
    @endforeach
</x-page-shell>
@endsection
