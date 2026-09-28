{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _reject_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Vorgelegte Fassung zurückweisen (MVP-995). Variablen: $document
--}}
<x-modal :title="__('procedure-documentation.action.reject')" :eyebrow="__('procedure-documentation.title') . ' ' . $document->displayVersion()"
         icon="undo" tone="warning" :action="route('finance.procedure-documentation.reject', $document)" method="POST"
         :form-data="['data-entry-form' => '']" :submit-label="__('procedure-documentation.action.reject')">
    <x-textarea-field name="review_note" :label="__('procedure-documentation.field.review_note')" required minlength="3" maxlength="500" rows="3">{{ old('review_note') }}</x-textarea-field>
</x-modal>
