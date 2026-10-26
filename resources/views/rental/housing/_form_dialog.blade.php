{{--
  Created on   : Mon Oct 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-modal :title="$profile->exists ? __('housing.edit') : __('housing.create')" icon="apartment"
    :action="$profile->exists ? route('rental.housing.update', $profile) : route('rental.housing.store')"
    :method="$profile->exists ? 'PUT' : 'POST'" :form-data="['data-entry-form' => '']" :submit-label="__('Speichern')">
    @include('rental.housing._form_body')
</x-modal>
