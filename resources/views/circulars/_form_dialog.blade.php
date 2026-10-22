{{--
  Created on   : Thu Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Rundschreiben entwerfen (Feature 119, MVP-608). Der Empfängerkreis wird hier
  nur gefiltert — versendet wird erst nach der Vorschau mit Empfängerzahl.
--}}
@php
    /** Vorlage (MVP-1285) belegt Inhalt, Filter und Schalter vor; ihre Anhänge gehen beim Speichern mit. */
    $prefill = $template !== null ? array_merge((array) ($template->filters ?? []), ['subject' => $template->subject, 'body' => $template->body, 'is_mandatory' => $template->is_mandatory, 'portal_notice' => $template->portal_notice]) : [];
@endphp
<x-modal
    :title="__('circular.action.create')"
    icon="campaign"
    :action="route('circulars.store')"
    method="POST"
    enctype="multipart/form-data"
    :submit-label="__('circular.action.save_draft')"
>
    @if ($template !== null)
        <input type="hidden" name="template" value="{{ $template->sqid }}">
        <p class="text-sm text-base-content/70">{{ __('circular.template.based_on', ['subject' => $template->subject]) }}</p>
    @endif
    <x-input-field name="subject" type="text" maxlength="191" required
                   :label="__('circular.column.subject')"
                   :value="old('subject', $prefill['subject'] ?? '')" />

    <x-textarea-field name="body" rows="10" required
                      :label="__('circular.field.body')"
                      :value="old('body', $prefill['body'] ?? '')"
                      :hint="__('circular.body_hint')" />

    <div>
        <label class="label" for="circular-attachment"><span class="label-text">{{ __('circular.field.attachment') }}</span></label>
        <input type="file" id="circular-attachment" name="attachment" class="file-input file-input-bordered file-input-sm w-full">
        <p class="mt-1 text-xs text-muted">{{ $template !== null && $template->attachments->isNotEmpty() ? __('circular.template.attachments', ['names' => $template->attachments->pluck('original_name')->implode(', ')]) : __('circular.attachment_hint') }}</p>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <x-input-field name="search" type="text" maxlength="191"
                       :label="__('circular.filter.search')"
                       :value="old('search', $prefill['search'] ?? '')" />
        <x-input-field name="city" type="text" maxlength="191"
                       :label="__('circular.filter.city')"
                       :value="old('city', $prefill['city'] ?? '')" />
        <x-input-field name="zip_prefix" type="text" maxlength="10"
                       :label="__('circular.filter.zip_prefix')"
                       :value="old('zip_prefix', $prefill['zip_prefix'] ?? '')"
                       :hint="__('circular.filter.zip_hint')" />
    </div>

    <x-checkbox-field name="with_active_projects" :label="__('circular.filter.with_active_projects')"
                      :checked="(bool) old('with_active_projects', $prefill['with_active_projects'] ?? false)" />

    <x-checkbox-field name="is_mandatory" :label="__('circular.field.is_mandatory')"
                      :hint="__('circular.mandatory_hint')"
                      :checked="(bool) old('is_mandatory', $prefill['is_mandatory'] ?? false)" />

    <x-checkbox-field name="portal_notice" :label="__('circular.field.portal_notice')"
                      :hint="__('circular.portal_hint')"
                      :checked="(bool) old('portal_notice', $prefill['portal_notice'] ?? false)" />
</x-modal>
