<?php
/*
 * Created on   : Wed Jul 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeExportPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\TimeExport\TimeExportStatus;
use App\Enums\User\Permission as P;
use App\Models\{TimeExport, User};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Berechtigungen für ApprovedTimeExporter (MVP-019, docs/zeit-export.md §7).
 */
class TimeExportPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::ExportTimeCreate->value)
            || $user->can(P::ExportTimeDeliver->value);
    }

    public function view(User $user, TimeExport $export): bool {
        return $user->organization_id === $export->organization_id
            && ($user->can(P::ExportTimeCreate->value)
                || $user->can(P::ExportTimeDeliver->value));
    }

    public function create(User $user): bool {
        return $user->can(P::ExportTimeCreate->value);
    }

    public function download(User $user, TimeExport $export): bool {
        return $user->organization_id === $export->organization_id
            && $export->status->isDownloadable()
            && ($user->can(P::ExportTimeCreate->value)
                || $user->can(P::ExportTimeDeliver->value));
    }

    public function deliver(User $user, TimeExport $export): bool {
        return $user->organization_id === $export->organization_id
            && $user->can(P::ExportTimeDeliver->value)
            && $export->status === TimeExportStatus::Ready;
    }

    public function reject(User $user, TimeExport $export): bool {
        return $user->organization_id === $export->organization_id
            && $user->can(P::ExportTimeDeliver->value)
            && in_array($export->status, [TimeExportStatus::Ready, TimeExportStatus::Delivered], true);
    }

    public function delete(User $user, TimeExport $export): bool {
        return $user->organization_id === $export->organization_id
            && $user->can(P::ExportTimeDelete->value)
            && $export->status !== TimeExportStatus::Delivered;
    }
}
