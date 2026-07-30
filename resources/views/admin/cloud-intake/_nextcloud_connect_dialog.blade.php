{{--
  Created on   : Wed Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _nextcloud_connect_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Nextcloud-Quelle per Zugangsdaten anbinden (Feature 080, MVP-382) --}}
{{-- @var \App\Models\CloudIntake\CloudDocumentConnection|null $connection --}}
<x-nextcloud-connect-dialog
    :connection="$connection"
    :action="route('admin.cloud-intake.nextcloud.connect')"
    icon="cloud"
    id-prefix="nc"
    lang-prefix="cloud_intake.nextcloud"
    name-key="cloud_intake.field.name"
/>
