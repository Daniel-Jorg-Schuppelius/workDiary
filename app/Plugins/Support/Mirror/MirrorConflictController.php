<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirrorConflictController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Mirror;

use App\Http\Controllers\Controller;
use App\Models\Integration\IntegrationInboxItem;
use App\Models\Platform\User;
use App\Support\ErrorText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Auflösung eines Spiegelkonflikts aus der Zuordnungs-Inbox (Feature 058,
 * MVP-127): überschreiben, als Version importieren oder die Spiegelung
 * trennen. Autorisierung wie die übrige Inbox (canManageBilling + Org-Grenze
 * + offener Eintrag); Fachlogik und auditierter Abschluss liegen im
 * {@see DocumentConflictResolver}.
 *
 * Basis der Dokumentspiegel (Konsolidierungs-Audit 2026-10, k2-12 — WebDAV
 * und SharePoint führten denselben Controller als Kopie). Die Ableitung nennt
 * Ziel und Plugin; die Meldungen liegen unter `<plugin>::<plugin>.conflict.flash.*`.
 */
abstract class MirrorConflictController extends Controller {
    public function __construct(private readonly DocumentConflictResolver $resolver) {}

    abstract protected function target(): MirrorTarget;

    /** Plugin-ID — zugleich Namensraum der Meldungen. */
    abstract protected function pluginId(): string;

    public function overwrite(IntegrationInboxItem $item): RedirectResponse {
        return $this->run($item, fn () => $this->resolver->overwrite($this->target(), $item), 'overwritten');
    }

    public function import(IntegrationInboxItem $item): RedirectResponse {
        return $this->run($item, fn () => $this->resolver->importAsVersion($this->target(), $item), 'imported');
    }

    public function detach(IntegrationInboxItem $item): RedirectResponse {
        return $this->run($item, fn () => $this->resolver->detach($this->target(), $item), 'detached');
    }

    private function run(IntegrationInboxItem $item, callable $action, string $flash): RedirectResponse {
        $this->guard($item);
        $texts = $this->pluginId() . '::' . $this->pluginId() . '.conflict.flash.';

        try {
            $action();
        } catch (Throwable $e) {
            return back()->with('error', __($texts . 'failed', ['reason' => ErrorText::for($e)]));
        }

        return back()->with('success', __($texts . $flash));
    }

    private function guard(IntegrationInboxItem $item): void {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->canManageBilling(), 403);
        abort_unless($item->organization_id === $user->organization_id, 404);
        abort_unless($item->plugin_id === $this->pluginId() && $item->case_type === IntegrationInboxItem::CASE_CONFLICT, 404);
        abort_unless($item->isOpen(), 422);
    }
}
