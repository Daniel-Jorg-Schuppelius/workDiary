{{--
  Created on   : Mon Oct 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _backfill_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: ältere Mails nachholen (MVP-1143) — Startpunkt zurückstellen, nur im Ordner dieses Eintrags. --}}
@php /** @var \App\Models\Mail\EmailConnection $connection */ @endphp
<x-modal
    :title="__('mail.backfill.title')"
    :eyebrow="$connection->name"
    icon="history"
    tone="primary"
    :action="route('admin.mail.connection.backfill', $connection)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('mail.backfill.submit')"
>
    <p class="text-sm">{{ __('mail.backfill.intro', ['folder' => $connection->folder]) }}</p>
    <x-input-field name="fetch_since" type="date" :label="__('mail.field.fetch_since')" required
                   :value="old('fetch_since', now()->subMonth()->toDateString())" :max="now()->toDateString()" />
    <ul class="list-disc space-y-1 pl-5 text-xs text-muted">
        <li>{{ __('mail.backfill.note_folder') }}</li>
        <li>{{ __('mail.backfill.note_inbox') }}</li>
        <li>{{ __('mail.backfill.note_duplicates') }}</li>
        @unless ($connection->usesImap())
            <li>{{ __('mail.backfill.note_graph_limit') }}</li>
        @endunless
    </ul>
</x-modal>
