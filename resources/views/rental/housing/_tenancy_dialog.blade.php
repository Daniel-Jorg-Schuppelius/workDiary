{{--
  Created on   : Mon Oct 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tenancy_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-modal :title="$tenancy->exists ? __('housing.tenancy_edit') : __('housing.tenancy_create')" icon="key"
    :action="$tenancy->exists ? route('rental.housing.tenancies.update', $tenancy) : route('rental.housing.tenancies.store')"
    :method="$tenancy->exists ? 'PUT' : 'POST'" :form-data="['data-entry-form' => '']" :submit-label="__('Speichern')">
    @include('rental.housing._tenancy_body')
</x-modal>
