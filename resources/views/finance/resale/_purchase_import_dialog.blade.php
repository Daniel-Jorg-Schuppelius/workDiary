{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _purchase_import_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Anbieterrechnungen als PDF importieren (Feature 152, MVP-762) — heute
  Quality Hosting: je Position Vertrag, Endkunde, Laufzeit, Betrag.
--}}
<x-modal
    :title="__('resale.purchase.import.title')"
    icon="picture_as_pdf"
    tone="primary"
    size="md"
    :action="route('finance.resale.purchases.import.store')"
    method="POST"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.purchase.import.submit')"
>
    <div class="text-sm text-base-content/70">{{ __('resale.purchase.import.hint') }}</div>
    <x-input-field name="files[]" id="purchase-files" type="file" :label="__('resale.purchase.import.files')" accept=".pdf,application/pdf" multiple error="files" />
</x-modal>
