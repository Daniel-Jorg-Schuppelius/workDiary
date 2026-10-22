<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccessAuditController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Access;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ListsAuditTrail;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Rollen- und Rechteänderungen der Organisation (MVP-1302): Rollen anlegen,
 * ändern, löschen, Rollen- und Direktrechte an Personen, Gruppenmitglieder.
 * Lesend für Support und Administration (`access.audit.view`).
 */
final class AccessAuditController extends Controller {
    use ListsAuditTrail;

    public function index(Request $request): View {
        Gate::authorize(Permission::AccessAuditView->value);

        return $this->auditTrail(
            $request,
            ['role.', 'user.role.', 'user.permission.', 'user_group.'],
            'admin.access.audit',
            __('access.audit.title'),
            __('access.audit.subtitle', ['org' => (string) $request->user()?->organization?->name]),
        );
    }
}
