{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Portal „Vereinbarungen" (Feature 157, MVP-822): eigene AVV-/NDA-Fassungen
     mit Stand; Dateien und Abschlussnachweis nur für freigegebene,
     vollständig unterzeichnete Fassungen. --}}
@extends('customer.layout')

@section('content')
    <h1 class="text-2xl font-semibold mb-1">{{ __('contract-signing.portal.title') }}</h1>
    <p class="text-sm text-muted mb-4">{{ __('contract-signing.portal.subtitle') }}</p>

    <x-table>
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('Vertragsart') }}</x-table.th>
                <x-table.th>{{ __('Titel') }}</x-table.th>
                <x-table.th>{{ __('contract-signing.customer_panel.revision') }}</x-table.th>
                <x-table.th>{{ __('contract-signing.field.status') }}</x-table.th>
                <x-table.th class="text-right">{{ __('contract-signing.portal.downloads') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($revisions as $revision)
            <tr>
                <td class="font-medium">{{ $revision->contract?->kind->label() }}</td>
                <td>{{ $revision->contract?->title }}<span class="block text-xs text-muted">{{ $revision->contract?->number }}</span></td>
                <td>{{ $revision->label() }}@if ($revision->effective_on)<span class="block text-xs text-muted">{{ __('contract-signing.revision.effective_on', ['date' => $revision->effective_on->fdate()]) }}</span>@endif</td>
                <td>
                    <x-status-badge size="sm" :tone="$revision->status->tone()" :label="$revision->status->label()" />
                    @if ($revision->completed_at)<span class="block text-xs text-muted">{{ $revision->completed_at->fdate() }}</span>@endif
                </td>
                <td class="text-right whitespace-nowrap">
                    @if ($revision->isReleasedToCustomer())
                        <x-button tone="outline" size="xs" icon="verified" :href="route('customer.agreements.certificate', ['revision' => $revision->sqid])">
                            <span>{{ __('contract-signing.action.certificate') }}</span>
                        </x-button>
                        <x-button tone="outline" size="xs" icon="folder_zip" :href="route('customer.agreements.package', ['revision' => $revision->sqid])">
                            <span>{{ __('contract-signing.action.package') }}</span>
                        </x-button>
                    @else
                        <span class="text-xs text-muted">{{ __('contract-signing.portal.not_released') }}</span>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty :colspan="5" :title="__('contract-signing.portal.empty')" />
        @endforelse
    </x-table>

    <x-pagination :paginator="$revisions" standing />
@endsection
