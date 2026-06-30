{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  ISMS-Advisories (Feature 044, MVP 2): importierte CSAF/VEX-Dokumente als
  Nachweis-Ablage mit SHA-256, Quelle der erzeugten Schwachstellen.
--}}

@extends('layouts.app')

@section('title', __('isms.title.advisories'))
@section('nav-title', __('isms.title.advisories'))

@section('content')
    <x-index-page :subtitle="__('isms.subtitle.advisories')">
        <x-slot:actions>
            @if ($canManage)
                <x-icon-btn icon="upload_file" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('isms.advisories.create')"
                            show-label>{{ __('isms.action.import_advisory') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        <p class="text-sm text-base-content/70">{{ __('isms.advisories.intro') }}</p>

        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('isms.field.title') }}</th>
                    <th>{{ __('isms.field.format') }}</th>
                    <th>{{ __('isms.field.document_id_ref') }}</th>
                    <th class="text-center">{{ __('isms.field.vuln_count') }}</th>
                    <th>{{ __('isms.field.imported_by') }}</th>
                    <th>{{ __('isms.field.imported_at') }}</th>
                    <th>{{ __('isms.field.file_hash') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($advisories as $advisory)
                <tr class="hover" id="isms-advisory-{{ $advisory->id }}">
                    <td class="font-medium">{{ $advisory->title }}</td>
                    <td><x-status-badge :tone="$advisory->format->tone()" outline>{{ $advisory->format->label() }}</x-status-badge></td>
                    <td class="font-mono text-xs">{{ $advisory->document_id_ref ?? '—' }}</td>
                    <td class="text-center">{{ $advisory->vulnerabilities_count }}</td>
                    <td class="text-base-content/70">{{ optional($advisory->importedBy)->name ?? '—' }}</td>
                    <td class="text-base-content/70">{{ $advisory->created_at?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td class="font-mono text-xs" title="{{ $advisory->file_hash }}">{{ \Illuminate\Support\Str::limit($advisory->file_hash, 12, '…') }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="7"
                               :title="__('isms.empty_advisories_title')"
                               :message="__('isms.empty_advisories')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$advisories" standing />
    </x-index-page>
@endsection
