{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $date, $users, $vehicles, $statuses --}}
@php
    $action = route('tours.store');
@endphp

<x-modal
    :title="__('Neue Tour')"
    :eyebrow="__('Touren')"
    icon="map"
    tone="primary"
    :action="$action"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">

    @include('tours._form_body')
</x-modal>
