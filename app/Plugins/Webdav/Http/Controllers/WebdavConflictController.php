<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebdavConflictController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Webdav\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{IntegrationInboxItem, User};
use App\Plugins\Support\Mirror\DocumentConflictResolver;
use App\Plugins\Webdav\{WebdavMirrorTarget, WebdavPlugin};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Auflösung eines WebDAV-Spiegelkonflikts aus der Zuordnungs-Inbox (Feature 058,
 * MVP-127, Rang 18): drei Aktionen (überschreiben / als Version importieren /
 * Spiegelung trennen). Autorisierung wie die übrige Inbox (canManageBilling +
 * Org-Grenze + offener Eintrag); die Fachlogik + der auditierte Abschluss
 * liegen seit A10/MVP-330 im gemeinsamen {@see DocumentConflictResolver}.
 */
class WebdavConflictController extends Controller {
    public function __construct(private readonly DocumentConflictResolver $resolver) {}

    public function overwrite(IntegrationInboxItem $item): RedirectResponse {
        return $this->run($item, fn () => $this->resolver->overwrite(new WebdavMirrorTarget(), $item), __('webdav.conflict.flash.overwritten'));
    }

    public function import(IntegrationInboxItem $item): RedirectResponse {
        return $this->run($item, fn () => $this->resolver->importAsVersion(new WebdavMirrorTarget(), $item), __('webdav.conflict.flash.imported'));
    }

    public function detach(IntegrationInboxItem $item): RedirectResponse {
        return $this->run($item, fn () => $this->resolver->detach(new WebdavMirrorTarget(), $item), __('webdav.conflict.flash.detached'));
    }

    private function run(IntegrationInboxItem $item, callable $action, string $success): RedirectResponse {
        $this->guard($item);

        try {
            $action();
        } catch (Throwable $e) {
            return back()->with('error', __('webdav.conflict.flash.failed', ['reason' => $e->getMessage()]));
        }

        return back()->with('success', $success);
    }

    private function guard(IntegrationInboxItem $item): void {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->canManageBilling(), 403);
        abort_unless($item->organization_id === $user->organization_id, 404);
        abort_unless($item->plugin_id === WebdavPlugin::ID && $item->case_type === IntegrationInboxItem::CASE_CONFLICT, 404);
        abort_unless($item->isOpen(), 422);
    }
}
