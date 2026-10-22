<?php
/*
 * Created on   : Sat Nov 22 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportAccessAuditController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ListsAuditTrail;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * MVP-004: Read-only Audit-Sicht für Supportzugriffe (`support.*`) der
 * eigenen Organisation. Filter: Datumsbereich, Event-Typ, Akteur.
 */
class SupportAccessAuditController extends Controller {
    use ListsAuditTrail;

    public function index(Request $request): View {
        Gate::authorize(Permission::PrivacySupportView->value);

        return $this->auditTrail(
            $request,
            ['support.'],
            'admin.support.access-audit.index',
            __('Supportzugriffe'),
            __('Audit-Spur aller Supportzugriffe (support.*) für :org.', ['org' => (string) $request->user()?->organization?->name]),
        );
    }
}
