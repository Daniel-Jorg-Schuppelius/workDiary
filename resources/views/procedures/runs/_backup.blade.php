{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _backup.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Backup-Karte eines Schritts (MVP-1566): Nachweis, Stand, Erfassen, Prüfen, Ablehnen. Variablen: $sr, $run, $proof, $isFinal, $runActive. --}}
@php
    /** @var \App\Models\Procedure\ProcedureStepRun $sr */
    /** @var \App\Models\Procedure\ProcedureBackupProof|null $proof */
    [$backupTone, $backupLabel] = match (true) {
        $proof === null => ['neutral', __('procedure.backup.status.missing')],
        $proof->verified => ['success', __('procedure.backup.status.verified', ['name' => $proof->verifiedBy?->name ?? '—'])],
        $proof->rejected_at !== null => ['error', __('procedure.backup.status.rejected', ['name' => $proof->verifiedBy?->name ?? '—'])],
        default => ['warning', __('procedure.backup.status.pending')],
    };
    $backupOpen = $runActive && ! $isFinal;
@endphp
<x-card padding="px-3 py-2" class="mt-3 space-y-2" data-backup-proof>
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <x-icon name="backup" class="text-muted" />
        <span class="font-medium">{{ __('procedure.backup.title') }}</span>
        <x-status-badge :tone="$backupTone" size="xs">{{ $backupLabel }}</x-status-badge>
    </div>

    @if ($proof !== null)
        <x-detail-grid layout="cells" cols="2" small-labels>
            <x-detail-grid.row :label="__('procedure.backup.source')">{{ $proof->source_label }} ({{ $proof->backup_scope->label() }})</x-detail-grid.row>
            <x-detail-grid.row :label="__('procedure.backup.taken_at')">{{ $proof->taken_at->fdatetime() }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('procedure.print.size')">{{ $proof->size_bytes?->format() ?? '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('procedure.backup.method')">{{ $proof->verify_method->label() }}</x-detail-grid.row>
            @if ($proof->registeredBy)
                <x-detail-grid.row :label="__('procedure.backup.registered_by')">{{ $proof->registeredBy->name }}</x-detail-grid.row>
            @endif
            @if ($proof->external_ref !== null)
                <x-detail-grid.row :label="__('procedure.backup.external_ref')">
                    @can('viewExternal', $proof)<span class="font-mono">{{ $proof->external_ref }}</span>@else{{ __('procedure.backup.external_hidden') }}@endcan
                </x-detail-grid.row>
            @endif
            @if ($proof->attachment !== null)
                <x-detail-grid.row :label="__('procedure.backup.file')">
                    <a class="link" href="{{ \App\Http\Controllers\Attachments\AttachmentController::downloadUrl($proof->attachment) }}">{{ $proof->attachment->original_name }}</a>
                </x-detail-grid.row>
            @endif
        </x-detail-grid>
        @if ($proof->verify_note)
            <p class="whitespace-pre-wrap text-xs text-base-content/70">{{ $proof->verify_note }}</p>
        @endif
    @endif

    @if ($backupOpen)
        <div class="flex flex-wrap items-start gap-2">
            @can('execute', $run)
                @can('register', [\App\Models\Procedure\ProcedureBackupProof::class, $sr])
                    <x-button tone="secondary" size="sm" icon="upload_file" data-entry-modal-trigger
                              :href="route('procedure-runs.steps.backup.form', [$run, $sr])">
                        {{ $proof === null ? __('procedure.backup.register') : __('procedure.backup.replace') }}
                    </x-button>
                @endcan
            @endcan
            @if ($proof !== null && ! $proof->verified)
                @can('verify', $proof)
                    <form method="POST" action="{{ route('procedure-runs.steps.backup.verify', [$run, $sr]) }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        @if ($proof->verify_method === \App\Enums\Procedure\ProcedureBackupVerifyMethod::Checksum)
                            <input type="text" name="checksum_probe" required maxlength="128"
                                   aria-label="{{ __('procedure.backup.checksum_probe') }}" placeholder="{{ __('procedure.backup.checksum_probe') }}"
                                   class="input input-bordered input-sm w-64 font-mono">
                        @else
                            <input type="text" name="note" maxlength="2000"
                                   aria-label="{{ __('procedure.backup.verify_note') }}" placeholder="{{ __('procedure.backup.verify_note') }}"
                                   class="input input-bordered input-sm w-64">
                        @endif
                        <x-button type="submit" tone="success" size="sm" icon="verified">{{ __('procedure.backup.verify') }}</x-button>
                    </form>
                    <details class="text-xs">
                        <summary class="cursor-pointer text-muted">{{ __('procedure.backup.reject') }}</summary>
                        <form method="POST" action="{{ route('procedure-runs.steps.backup.reject', [$run, $sr]) }}" class="mt-2 space-y-2">
                            @csrf
                            <textarea aria-label="{{ __('procedure.backup.reject_reason') }}" name="reason" rows="2" minlength="5" maxlength="2000" required
                                      class="textarea textarea-bordered textarea-sm w-full"
                                      placeholder="{{ __('procedure.backup.reject_reason') }}"></textarea>
                            <x-button type="submit" tone="warning" size="xs">{{ __('procedure.backup.reject_confirm') }}</x-button>
                        </form>
                    </details>
                @endcan
            @endif
        </div>
    @endif
</x-card>
