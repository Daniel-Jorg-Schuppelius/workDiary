{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog wrapper for Invoice create --}}
<x-modal
    :title="__('Rechnung aus Zeiteinträgen erstellen')"
    :eyebrow="__('Neue Rechnung')"
    icon="receipt_long"
    tone="primary"
    :action="route('invoices.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Entwurf erstellen')"
>
    @include('invoices._form_body')
</x-modal>
