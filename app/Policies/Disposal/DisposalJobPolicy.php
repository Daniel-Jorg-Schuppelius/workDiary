<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalJobPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Disposal;

use App\Enums\User\Permission as P;
use App\Models\Disposal\DisposalJob;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

/**
 * Policy der Entsorgungsakte (Feature 100). Kind-Objekte (Positionen,
 * Behandlungen, Übergaben) werden gegen die Akte autorisiert; Org-Scoping
 * läuft global über BelongsToOrganization + Sqid-Binding.
 */
class DisposalJobPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::DisposalViewAny,
        'view' => P::DisposalView,
        'create' => P::DisposalManage,
        'update' => P::DisposalManage,
        'complete' => P::DisposalComplete,
    ];

    /** Bewachter Abschluss (Kundennachweis) und Storno — Leitungsaufgabe. */
    public function complete(User $user, DisposalJob $job): bool {
        return $this->allows($user, 'complete');
    }
}
