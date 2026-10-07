{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Anfragen und Aufträge (MVP-1074) — erwartet: $intakes, $stages, $hasUploadTargets --}}
@extends('customer.layout')

@section('content')
    <h1 class="mb-2 text-2xl font-semibold">{{ __('customer_intake.portal.title') }}</h1>
    <p class="mb-4 text-sm text-base-content/70">{{ __('customer_intake.portal.intro') }}</p>

    <div class="mb-4 flex flex-wrap gap-2">
        <x-button :href="route('customer.intakes.create', ['kind' => 'print'])" icon="print">{{ __('customer_intake.portal.request_print') }}</x-button>
        <x-button :href="route('customer.intakes.create', ['kind' => 'it'])" icon="computer">{{ __('customer_intake.portal.request_it') }}</x-button>
        @if ($hasUploadTargets)
            <x-button :href="route('customer.intakes.upload')" tone="outline" icon="upload_file">{{ __('customer_intake.portal.upload_files') }}</x-button>
        @endif
    </div>

    <x-table>
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('Nummer') }}</x-table.th>
                <x-table.th>{{ __('customer_intake.field.subject') }}</x-table.th>
                <x-table.th>{{ __('customer_intake.field.kind') }}</x-table.th>
                <x-table.th>{{ __('customer_intake.field.stage') }}</x-table.th>
                <x-table.th>{{ __('customer_intake.field.received_at') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($intakes as $intake)
            @php($stage = $stages->for($intake))
            <tr>
                <td class="whitespace-nowrap font-mono text-sm"><a class="link link-hover" href="{{ route('customer.intakes.show', $intake) }}">{{ $intake->number }}</a></td>
                <td>{{ $intake->subject }}</td>
                <td>{{ $intake->kind->label() }}</td>
                <td>
                    <x-status-badge :tone="$stage->tone">{{ $stage->label }}</x-status-badge>
                    @if ($stage->actionRequired)
                        <span class="block text-xs text-warning">{{ $stage->nextStep }}</span>
                    @endif
                </td>
                <td class="whitespace-nowrap">{{ $intake->created_at?->fdate() }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="5" :title="__('customer_intake.portal.empty')" />
        @endforelse
    </x-table>

    <x-pagination :paginator="$intakes" standing />
@endsection
