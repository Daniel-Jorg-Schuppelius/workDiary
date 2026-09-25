{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _template_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Protokoll als Vorlage speichern oder die Herkunftsvorlage
     fortschreiben (MVP-901). Übernommen werden Punkte und Konfiguration,
     nie die erfassten Werte. --}}
<x-modal
    :title="__('protocol.template.save_title')"
    :eyebrow="$protocol->title"
    icon="library_add"
    tone="primary"
    :action="route('protocols.as-template', $protocol)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('protocol.template.save')">
    <p class="text-sm text-muted">{{ __('protocol.template.save_hint') }}</p>
    @if ($existing)
        <x-checkbox-field name="update_existing" :label="__('protocol.template.update_existing', ['name' => $existing->name, 'version' => $existing->version + 1])" :checked="(bool) old('update_existing', true)" />
    @endif
    @include('protocol-templates._fields', ['template' => $existing])
</x-modal>
