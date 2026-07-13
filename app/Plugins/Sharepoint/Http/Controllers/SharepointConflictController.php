<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SharepointConflictController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Sharepoint\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{IntegrationInboxItem, User};
use App\Plugins\Sharepoint\{SharepointMirrorTarget, SharepointPlugin};
use App\Plugins\Support\Mirror\DocumentConflictResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Auflösung eines SharePoint-Spiegelkonflikts aus der Zuordnungs-Inbox
 * (MVP-330, Bauturbo A10; Semantik = WebDAV Rang 18): drei Aktionen
 * (überschreiben / als Version importieren / Spiegelung trennen).
 * Autorisierung wie die übrige Inbox (canManageBilling + Org-Grenze +
 * offener Eintrag); die Fachlogik + der auditierte Abschluss liegen im
 * gemeinsamen {@see DocumentConflictResolver}.
 */
class SharepointConflictController extends Controller {
    public function __construct(private readonly DocumentConflictResolver $resolver) {}

    public function overwrite(IntegrationInboxItem $item): RedirectResponse {
        return $this->run($item, fn () => $this->resolver->overwrite(new SharepointMirrorTarget(), $item), __('sharepoint.conflict.flash.overwritten'));
    }

    public function import(IntegrationInboxItem $item): RedirectResponse {
        return $this->run($item, fn () => $this->resolver->importAsVersion(new SharepointMirrorTarget(), $item), __('sharepoint.conflict.flash.imported'));
    }

    public function detach(IntegrationInboxItem $item): RedirectResponse {
        return $this->run($item, fn () => $this->resolver->detach(new SharepointMirrorTarget(), $item), __('sharepoint.conflict.flash.detached'));
    }

    private function run(IntegrationInboxItem $item, callable $action, string $success): RedirectResponse {
        $this->guard($item);

        try {
            $action();
        } catch (Throwable $e) {
            return back()->with('error', __('sharepoint.conflict.flash.failed', ['reason' => $e->getMessage()]));
        }

        return back()->with('success', $success);
    }

    private function guard(IntegrationInboxItem $item): void {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->canManageBilling(), 403);
        abort_unless($item->organization_id === $user->organization_id, 404);
        abort_unless($item->plugin_id === SharepointPlugin::ID && $item->case_type === IntegrationInboxItem::CASE_CONFLICT, 404);
        abort_unless($item->isOpen(), 422);
    }
}
