{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _show_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Detaildialog eines offenen Punkts (MVP-1597). Erwartet: $issue, $canUpload --}}
<x-modal
    :title="$issue->title"
    :eyebrow="__('open-issue.title.index')"
    icon="flag"
    :badge="$issue->status->label()"
    :badge-tone="$issue->status->tone()"
    tone="primary"
    size="lg">
    <div class="space-y-4">
        <x-detail-grid layout="cells" small-labels>
            <x-detail-grid.row :label="__('open-issue.field.severity')" :value="$issue->severity->label()" />
            <x-detail-grid.row :label="__('open-issue.field.visibility')" :value="$issue->visibility->label()" />
            <x-detail-grid.row :label="__('open-issue.field.assignee')" :value="$issue->assignee?->name" />
            <x-detail-grid.row :label="__('open-issue.field.due_at')" :value="$issue->due_at?->fdatetime()" />
            <x-detail-grid.row :label="__('open-issue.field.creator')" :value="$issue->creator?->name" />
            <x-detail-grid.row :label="__('open-issue.field.category')" :value="$issue->category" />
            @if ($issue->closed_at)
                <x-detail-grid.row :label="__('open-issue.field.closed_at')" :value="$issue->closed_at->fdatetime()" />
                <x-detail-grid.row :label="__('open-issue.field.reason')" :value="$issue->closed_reason" />
            @endif
        </x-detail-grid>
        @if ($issue->description)
            <p class="whitespace-pre-wrap text-sm text-base-content/80">{{ $issue->description }}</p>
        @endif

        <x-card as="section" padding="p-4">
            @include('comments._thread', [
                'parent' => $issue,
                'storeRoute' => route('open-issues.comments.store', $issue),
            ])
        </x-card>

        <x-attachments-section :attachments="$issue->attachments" upload-type="open-issue" :upload-id="$issue->sqid"
                               :can-upload="$canUpload" :can-delete="! $issue->attachmentsLocked()" details />

        <x-card as="section" :title="__('open-issue.title.history')" icon="history">
            <x-journal :entries="$issue->journal" newest-first :payload-keys="['reason', 'resolution', 'name', 'ticket_no']" />
        </x-card>
    </div>

    <x-slot:actions>
        <x-button type="button" tone="ghost" class="gap-2" data-entry-modal-close icon="close">{{ __('Schließen') }}</x-button>
        @if (! $issue->closed_at)
            @can('update', $issue)
                <x-button :href="route('open-issues.edit', $issue)" tone="primary" class="gap-2" data-entry-modal-trigger icon="edit">{{ __('open-issue.action.edit') }}</x-button>
            @endcan
        @endif
    </x-slot:actions>
</x-modal>
