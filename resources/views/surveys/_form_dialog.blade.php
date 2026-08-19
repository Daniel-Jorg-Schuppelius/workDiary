{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

<x-modal
    :title="__('Fragebogen anlegen')"
    :eyebrow="__('Umfragen')"
    icon="reviews"
    tone="primary"
    :action="route('surveys.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">

    <x-input-field name="title" :label="__('Titel')" required placeholder="{{ __('z. B. Jahres-NPS 2026') }}" />
    <x-input-field name="purpose" :label="__('Zweck / Einleitungstext')"
                   :hint="__('Erscheint in der Einladung und über dem Fragebogen.')" />
    {{-- Anonymität ist eine Speicher-Eigenschaft und friert mit der ersten
         Einladung ein — rückwirkend umschalten wäre eine Lüge gegenüber den
         bisherigen Teilnehmern. --}}
    <x-checkbox-field name="anonymous" :label="__('Anonym')"
                      :hint="__('Antworten werden ohne Personenbezug gespeichert — technisch nicht rückführbar.')" />
    <x-checkbox-field name="trigger_on_ticket_close" :label="__('Nach Ticketabschluss automatisch einladen')"
                      :hint="__('Der Ermüdungsschutz (Standard: 90 Tage je Adresse) gilt immer.')" />
</x-modal>
