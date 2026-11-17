{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _approval_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Datenschutzfreigabe einer KI-Verbindung (Feature 025, MVP-1672).
     Cloud: AVV mit Dokument, EU, kein Training. Lokal: Betrieb im eigenen Haus,
     EU und kein Training bestätigen, AVV nur bei einem Dienstleister. --}}
@php
    /** @var \App\Models\Ai\AiProviderConnection $connection */
    /** @var \App\Enums\Ai\AiApprovalState $state */
    $isCloud = $connection->isCloud();
@endphp
<x-modal
    :title="__('ai.privacy.approval.title', ['name' => $connection->name])"
    :eyebrow="__('ai.privacy.title')"
    icon="verified_user"
    tone="primary"
    :action="route('admin.ai.approval.store', $connection)"
    method="POST"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="$state === \App\Enums\Ai\AiApprovalState::Missing ? __('ai.privacy.approval.submit') : __('ai.privacy.approval.renew')"
>
    <div class="space-y-1 text-sm">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-medium">{{ $connection->provider->label() }}</span>
            <x-status-badge :tone="$isCloud ? 'warning' : 'success'" size="sm">{{ $isCloud ? __('ai.field.cloud') : __('ai.field.local') }}</x-status-badge>
            <x-status-badge :tone="$state->badge()" size="sm">{{ $state->label() }}</x-status-badge>
        </div>
        @if ($connection->privacy_reviewed_at !== null)
            <p class="text-xs text-muted">
                {{ __('ai.privacy.approval.reviewed', [
                    'date' => $connection->privacy_reviewed_at->isoFormat('L'),
                    'name' => $reviewer?->name ?? '—',
                ]) }}
                @if ($expiresAt !== null)
                    · {{ __('ai.privacy.approval.expires', ['date' => $expiresAt->isoFormat('L')]) }}
                @endif
            </p>
        @endif
    </div>

    <x-form-group :legend="__('ai.privacy.approval.legend')" icon="verified_user" tone="primary" cols="1"
                  :description="$isCloud ? __('ai.privacy.approval.description_cloud') : __('ai.privacy.approval.description_local')">
        <x-checkbox-field name="has_dpa" :toggle="false" :label="__('ai.privacy.confirm.dpa')"
                          :checked="(bool) old('has_dpa', $connection->has_dpa)"
                          :hint="$isCloud ? __('ai.privacy.confirm.dpa_help_cloud') : __('ai.privacy.confirm.dpa_help_local')" />
        <x-input-field name="dpa_document" type="file"
                       :label="$document !== null ? __('ai.privacy.field.dpa_document_replace') : __('ai.privacy.field.dpa_document')"
                       :hint="__('ai.privacy.field.dpa_document_help', ['mb' => \App\Services\Attachments\FileAttacher::maxMb()])" />
        @if ($document !== null)
            <p class="text-xs">
                <a class="link" href="{{ \App\Http\Controllers\Attachments\AttachmentController::downloadUrl($document) }}">{{ $document->original_name }}</a>
                <span class="text-muted">· {{ $document->created_at?->isoFormat('L') }}</span>
            </p>
        @endif
        <x-checkbox-field name="is_eu_processing" :toggle="false" :label="__('ai.privacy.confirm.eu')"
                          :checked="(bool) old('is_eu_processing', $connection->is_eu_processing)"
                          :hint="__('ai.privacy.confirm.eu_help')" />
        <x-checkbox-field name="is_training_excluded" :toggle="false" :label="__('ai.privacy.confirm.training')"
                          :checked="(bool) old('is_training_excluded', $connection->is_training_excluded)"
                          :hint="__('ai.privacy.confirm.training_help')" />
    </x-form-group>

    @if ($connection->privacy_reviewed_at !== null)
        <x-slot:footerExtra>
            <x-action-form :action="route('admin.ai.approval.destroy', $connection)" method="DELETE"
                           :confirm="__('ai.privacy.approval.revoke_confirm')" confirm-tone="error">
                <x-icon-btn icon="gpp_bad" tone="error" size="sm" type="submit" show-label>{{ __('ai.privacy.approval.revoke') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
