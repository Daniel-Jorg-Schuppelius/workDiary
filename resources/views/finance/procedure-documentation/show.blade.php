{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Detail einer Verfahrensdokumentations-Version (Feature 134): Freitext-
  Pflichtteile des Betreibers + generierter Systemteil (Entwurf: Live-
  Vorschau, veröffentlicht: eingefrorener Snapshot) + Nachweise.
--}}
@extends('layouts.app')
@section('title', __('procedure-documentation.title') . ' ' . $document->displayVersion())
@section('nav-title', __('procedure-documentation.title') . ' ' . $document->displayVersion())
@section('content')
@php
    $editable = $canManage && $document->isEditable();
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$document->isPublished() ? __('procedure-documentation.generated.frozen_hint', ['date' => $document->published_at?->fdatetime()]) : __('procedure-documentation.generated.preview_hint')"
                        :badge="$document->status->label()" :badgeTone="$document->status->tone()">
            <x-slot:actions>
                @if ($editable)
                    <x-icon-btn icon="edit" tone="outline" size="sm"
                                data-entry-modal-trigger
                                :href="route('finance.procedure-documentation.edit', $document)"
                                show-label>{{ __('procedure-documentation.action.edit') }}</x-icon-btn>
                    <x-action-form :action="route('finance.procedure-documentation.publish', $document)"
                                   :confirm="__('procedure-documentation.confirm.publish')" confirm-icon="verified" confirm-tone="primary">
                        <x-icon-btn type="submit" icon="verified" tone="primary" size="sm" show-label>{{ __('procedure-documentation.action.publish') }}</x-icon-btn>
                    </x-action-form>
                    <x-action-form :action="route('finance.procedure-documentation.destroy', $document)" method="DELETE"
                                   :confirm="__('procedure-documentation.confirm.delete')" confirm-icon="delete" confirm-tone="error">
                        <x-icon-btn type="submit" icon="delete" tone="ghost" size="sm" :label="__('procedure-documentation.action.delete')" />
                    </x-action-form>
                @endif
                @if ($document->isPublished())
                    <x-icon-btn icon="download" tone="primary" size="sm"
                                :href="route('finance.procedure-documentation.download', $document)"
                                show-label>{{ __('procedure-documentation.action.download') }}</x-icon-btn>
                @endif
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('finance.procedure-documentation.index')"
                            show-label>{{ __('procedure-documentation.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('procedure-documentation.pdf.part_operator')" icon="description">
                @foreach (\App\Models\Finance\ProcedureDocumentation::TEXT_FIELDS as $field)
                    <h3 class="mt-3 text-sm font-semibold first:mt-0">{{ __('procedure-documentation.text.' . $field) }}</h3>
                    @if (filled($document->{$field}))
                        <p class="whitespace-pre-line text-sm">{{ $document->{$field} }}</p>
                    @else
                        <p class="text-sm text-muted">{{ __('procedure-documentation.generated.empty_text') }}</p>
                    @endif
                @endforeach
            </x-card>

            @include('finance.procedure-documentation._sections', ['payload' => $payload])
        </div>

        <div class="space-y-4">
            <x-card :title="__('procedure-documentation.pdf.proof')" icon="verified">
                <x-detail-grid>
                    <x-detail-grid.row :label="__('procedure-documentation.field.version')" :value="$document->displayVersion()" />
                    <x-detail-grid.row :label="__('procedure-documentation.field.status')" :value="$document->status->label()" />
                    <x-detail-grid.row :label="__('procedure-documentation.field.created')" :value="($document->created_at?->fdatetime() ?? '–') . ($document->createdBy ? ' · ' . $document->createdBy->name : '')" />
                    <x-detail-grid.row :label="__('procedure-documentation.field.published')" :value="($document->published_at?->fdatetime() ?? '–') . ($document->publishedBy ? ' · ' . $document->publishedBy->name : '')" />
                    <x-detail-grid.row :label="__('procedure-documentation.field.generated_at')" :value="isset($payload['generated_at']) ? \Illuminate\Support\Carbon::parse($payload['generated_at'])->fdatetime() : '–'" />
                    <x-detail-grid.row :label="__('procedure-documentation.field.chains_verified')" :value="! empty($payload['chains_verified']) ? __('procedure-documentation.yes') : __('procedure-documentation.generated.chains_pending')" />
                    <x-detail-grid.row :label="__('procedure-documentation.field.snapshot_sha256')">
                        <span class="break-all font-mono text-xs">{{ $document->snapshot_sha256 ?? '–' }}</span>
                    </x-detail-grid.row>
                    <x-detail-grid.row :label="__('procedure-documentation.field.pdf_sha256')">
                        <span class="break-all font-mono text-xs">{{ $document->pdf_sha256 ?? '–' }}</span>
                    </x-detail-grid.row>
                </x-detail-grid>
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection
