{{--
  Created on   : Wed Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _nextcloud_connect_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Nextcloud-Backupziel per Zugangsdaten anbinden (Feature 017, MVP-383) --}}
{{-- @var \App\Models\Backup\BackupTargetConnection|null $connection --}}
<x-nextcloud-connect-dialog
    :connection="$connection"
    :action="route('admin.backup-targets.nextcloud.connect')"
    icon="cloud_upload"
    id-prefix="ncb"
    lang-prefix="backup_targets.nextcloud"
/>
