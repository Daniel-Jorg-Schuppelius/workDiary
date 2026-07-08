<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChatAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Notification\ChatWebhookDeliveryJob;
use App\Models\{ChatWebhook, Organization, User};
use App\Services\Notification\ChatMessageFormatter;
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

/**
 * Admin-Verwaltung der ausgehenden Team-Messenger-Kanäle (Feature 056, MVP-119):
 * Microsoft-Teams-/Mattermost-Incoming-Webhooks je Organisation. Die Kanal-URL
 * ist verschlüsselt at-rest und erscheint nie in Views/Audit
 * ({@see ChatWebhook::$hidden}). Welche Ereignisse an die Kanäle gehen, wird über
 * die bestehende Benachrichtigungsmatrix (admin.notification-rules) gesteuert.
 */
class ChatAdminController extends Controller {
    private const KINDS = [ChatWebhook::KIND_TEAMS, ChatWebhook::KIND_MATTERMOST];

    public function index(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        return view('admin.chat.index', [
            'webhooks' => ChatWebhook::query()
                ->where('organization_id', $organization->id)
                ->orderBy('name')
                ->get(),
            'kinds' => self::KINDS,
        ]);
    }

    /** Legt einen Kanal an (Teams/Mattermost-Webhook-URL, verschlüsselt gespeichert). */
    public function store(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'in:' . implode(',', self::KINDS)],
            'webhook_url' => ['required', 'url', 'max:2000'],
        ]);

        $url = trim((string) $data['webhook_url']);
        if (! str_starts_with($url, 'https://')) {
            return back()->with('error', __('chat.flash.invalid_url'))->withInput();
        }

        $webhook = ChatWebhook::query()->create([
            'organization_id' => $organization->id,
            'name' => (string) $data['name'],
            'kind' => (string) $data['kind'],
            'webhook_url' => $url,
            'active' => true,
            'created_by' => $admin->id,
        ]);
        $webhook->audit('chat.webhook_created', ['by_user_id' => (int) $admin->id, 'kind' => $webhook->kind]);

        return back()->with('success', __('chat.flash.saved'));
    }

    /** Deaktiviert einen Kanal. */
    public function disconnect(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $webhook = $this->resolveWebhook($organization, (string) $request->input('webhook', ''));
        abort_unless($webhook instanceof ChatWebhook, 404);

        $webhook->forceFill(['active' => false])->save();
        $webhook->audit('chat.disconnected', ['by_user_id' => (int) $admin->id]);

        return back()->with('success', __('chat.flash.disconnected'));
    }

    /** Sendet eine Testnachricht an den Kanal (synchron, sofortiges Feedback). */
    public function test(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $webhook = $this->resolveWebhook($organization, (string) $request->input('webhook', ''));
        abort_unless($webhook instanceof ChatWebhook, 404);
        abort_unless($webhook->isActive(), 422, __('chat.flash.test_inactive'));

        try {
            // Bestehenden Zustellweg wiederverwenden (SSRF-Guard, Auto-Disable,
            // Formatierung je Kanaltyp) — synchron für direktes Feedback.
            (new ChatWebhookDeliveryJob(
                (int) $webhook->id,
                (string) __('chat.test.event'),
                ['title' => (string) __('chat.test.title'), 'message' => (string) __('chat.test.message'), 'url' => null],
            ))->handle(app(ChatMessageFormatter::class));
        } catch (Throwable) {
            return back()->with('error', __('chat.flash.test_failed'));
        }

        return back()->with('success', __('chat.flash.test_sent'));
    }

    private function resolveWebhook(Organization $organization, string $sqid): ?ChatWebhook {
        $decoded = app(SqidEncoder::class)->decode(ChatWebhook::class, $sqid);

        return $decoded !== null
            ? ChatWebhook::query()->whereKey($decoded)->where('organization_id', $organization->id)->first()
            : null;
    }

    private function admin(): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->isAdmin(), 403);
        abort_unless($user->organization_id !== null, 422, 'Kein Organisationskontext.');

        return $user;
    }

    private function organization(User $admin): Organization {
        $org = $admin->organization;
        abort_unless($org instanceof Organization, 422, 'Kein Organisationskontext.');

        return $org;
    }
}
