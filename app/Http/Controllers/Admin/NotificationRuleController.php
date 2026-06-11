<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotificationRuleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Enums\User\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Notification\NotificationRule;
use App\Models\{Organization, User};
use App\Support\Sqid;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Admin-UI für Benachrichtigungsregeln (MVP-018): eine Zeile pro Ereignistyp
 * aus der NotificationEvent-Registry, Bearbeitung als Modal. Ohne gespeicherte
 * Zeile gilt der Code-Default des Events (siehe NotificationRule::resolveFor).
 */
class NotificationRuleController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', NotificationRule::class);
        $organizationId = $this->currentOrganizationId();

        $rules = collect(NotificationEvent::cases())
            ->map(fn(NotificationEvent $event): NotificationRule => NotificationRule::resolveFor($organizationId, $event));

        return view('admin.notification-rules.index', [
            'rules' => $rules,
            'canUpdate' => Gate::allows('update', NotificationRule::class),
        ]);
    }

    public function edit(string $event): View {
        Gate::authorize('update', NotificationRule::class);
        $eventEnum = $this->resolveEvent($event);

        return view('admin.notification-rules._form_dialog', [
            'rule' => NotificationRule::resolveFor($this->currentOrganizationId(), $eventEnum),
            'event' => $eventEnum,
            'roleOptions' => $this->roleOptions(),
            'userOptions' => $this->userOptions(),
        ]);
    }

    public function update(Request $request, string $event): RedirectResponse {
        Gate::authorize('update', NotificationRule::class);
        $eventEnum = $this->resolveEvent($event);
        $organizationId = $this->currentOrganizationId();

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'channels' => ['array'],
            'channels.*' => [Rule::in(NotificationChannel::values())],
            'notify_affected' => ['required', 'boolean'],
            'recipient_roles' => ['array'],
            'recipient_roles.*' => [Rule::in(UserRole::values())],
            'recipient_users' => ['array'],
            'recipient_users.*' => ['string'],
            'escalation_enabled' => ['required', 'boolean'],
            'escalate_after_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'escalation_role' => ['nullable', Rule::in(UserRole::values())],
        ]);

        // Feste Zusatz-Empfänger kommen als Sqids (nie rohe IDs im Formular).
        $userIds = collect((array) ($data['recipient_users'] ?? []))
            ->map(fn(string $sqid): ?int => Sqid::decode(User::class, $sqid))
            ->filter()
            ->values()
            ->all();

        $rule = NotificationRule::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('event', $eventEnum->value)
            ->first() ?? new NotificationRule;

        $rule->fill([
            'event' => $eventEnum->value,
            'enabled' => (bool) $data['enabled'],
            'channels' => array_values((array) ($data['channels'] ?? [])),
            'notify_affected' => (bool) $data['notify_affected'],
            'recipient_roles' => array_values((array) ($data['recipient_roles'] ?? [])),
            'recipient_user_ids' => $userIds,
            'escalation_enabled' => (bool) $data['escalation_enabled'],
            'escalate_after_hours' => $data['escalate_after_hours'] ?? null,
            'escalation_role' => $data['escalation_role'] ?? null,
        ]);
        $rule->organization_id = $organizationId;
        $rule->save();

        return redirect()->route('admin.notification-rules.index')
            ->with('success', __('notification.flash.rule_saved', ['event' => $eventEnum->label()]));
    }

    private function resolveEvent(string $event): NotificationEvent {
        return NotificationEvent::tryFrom($event) ?? abort(404);
    }

    private function currentOrganizationId(): int {
        if (app()->bound('currentOrganization')) {
            $org = app('currentOrganization');
            if ($org instanceof Organization) {
                return (int) $org->id;
            }
        }

        $orgId = $this->authUser()->organization_id;
        abort_if($orgId === null, 404);

        return (int) $orgId;
    }

    /** @return array<string, string> Rollen-Optionen (interne Rollen, ohne Kunde). */
    private function roleOptions(): array {
        return collect(UserRole::cases())
            ->reject(fn(UserRole $role): bool => $role === UserRole::Kunde)
            ->mapWithKeys(fn(UserRole $role): array => [$role->value => $role->label()])
            ->all();
    }

    /** @return array<string, string> Sqid => Name der Org-Mitglieder. */
    private function userOptions(): array {
        return User::query()
            ->where('organization_id', $this->currentOrganizationId())
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn(User $user): array => [Sqid::encode(User::class, $user->id) => (string) $user->name])
            ->all();
    }
}
