{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Variablen: $survey (null = neu), $anonymityLocked; beim Bearbeiten nur der Kopf (MVP-1610), Auslöser haben eine eigene Karte. --}}
<x-modal
    :title="$survey === null ? __('Fragebogen anlegen') : __('survey.header.edit_title')"
    :eyebrow="__('Umfragen')"
    icon="reviews"
    tone="primary"
    :action="$survey === null ? route('surveys.store') : route('surveys.update', $survey)"
    :method="$survey === null ? 'POST' : 'PUT'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$survey === null ? __('Anlegen') : __('Speichern')">

    <x-input-field name="title" :label="__('Titel')" :value="old('title', $survey?->title)" required maxlength="160" placeholder="{{ __('z. B. Jahres-NPS 2026') }}" />
    <x-input-field name="purpose" :label="__('Zweck / Einleitungstext')" :value="old('purpose', $survey?->purpose)" maxlength="500"
                   :hint="__('Erscheint in der Einladung und über dem Fragebogen.')" />
    {{-- Anonymität ist eine Speicher-Eigenschaft und friert mit der ersten
         Einladung ein — rückwirkend umschalten wäre eine Lüge gegenüber den
         bisherigen Teilnehmern. --}}
    <x-checkbox-field name="anonymous" :label="__('Anonym')" :checked="(bool) old('anonymous', $survey?->anonymous)" :disabled="$anonymityLocked"
                      :hint="$anonymityLocked ? __('survey.header.anonymous_locked') : __('Antworten werden ohne Personenbezug gespeichert — technisch nicht rückführbar.')" />
    @if ($survey === null)
        <x-checkbox-field name="trigger_on_ticket_close" :label="__('Nach Ticketabschluss automatisch einladen')"
                          :hint="__('Der Ermüdungsschutz (Standard: 90 Tage je Adresse) gilt immer.')" />
        <x-checkbox-field name="trigger_on_order_completion" :label="__('survey.triggers.order')" />
        <x-input-field name="trigger_schedule_days" type="number" min="7" max="730" :label="__('survey.triggers.schedule')"
                       :hint="__('survey.triggers.scheduleHint')" />
    @endif
</x-modal>
