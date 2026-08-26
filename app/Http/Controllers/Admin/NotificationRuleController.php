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
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Notification\NotificationRule;
use App\Models\User;
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
    use ResolvesCurrentOrganization;

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
            // Eskalationsleiter Stufe 2/3 (MVP-331): je eigene Frist + Empfängergruppe.
            'escalation2_after_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'escalation2_roles' => ['array'],
            'escalation2_roles.*' => [Rule::in(UserRole::values())],
            'escalation2_users' => ['array'],
            'escalation2_users.*' => ['string'],
            'escalation3_after_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'escalation3_roles' => ['array'],
            'escalation3_roles.*' => [Rule::in(UserRole::values())],
            'escalation3_users' => ['array'],
            'escalation3_users.*' => ['string'],
        ]);

        // Feste (Zusatz-)Empfänger kommen als Sqids (nie rohe IDs im Formular).
        $decodeUsers = static fn(array $sqids): array => collect($sqids)
            ->map(fn(string $sqid): ?int => Sqid::decode(User::class, $sqid))
            ->filter()
            ->values()
            ->all();
        $userIds = $decodeUsers((array) ($data['recipient_users'] ?? []));

        $rule = NotificationRule::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('event', $eventEnum->value)
            ->first() ?? new NotificationRule;

        // SMS (Feature 147) ist nur an kritischen Ereignissen erlaubt — sonst
        // ließe sich der kostenpflichtige Kanal über ein manipuliertes
        // Formular an jede Fristenmeldung hängen.
        $channels = array_values(array_filter(
            (array) ($data['channels'] ?? []),
            static fn (string $channel): bool => $channel !== NotificationChannel::Sms->value || $eventEnum->supportsSms(),
        ));

        $rule->fill([
            'event' => $eventEnum->value,
            'enabled' => (bool) $data['enabled'],
            'channels' => $channels,
            'notify_affected' => (bool) $data['notify_affected'],
            'recipient_roles' => array_values((array) ($data['recipient_roles'] ?? [])),
            'recipient_user_ids' => $userIds,
            'escalation_enabled' => (bool) $data['escalation_enabled'],
            'escalate_after_hours' => $data['escalate_after_hours'] ?? null,
            'escalation_role' => $data['escalation_role'] ?? null,
            'escalation2_after_hours' => $data['escalation2_after_hours'] ?? null,
            'escalation2_roles' => array_values((array) ($data['escalation2_roles'] ?? [])),
            'escalation2_user_ids' => $decodeUsers((array) ($data['escalation2_users'] ?? [])),
            'escalation3_after_hours' => $data['escalation3_after_hours'] ?? null,
            'escalation3_roles' => array_values((array) ($data['escalation3_roles'] ?? [])),
            'escalation3_user_ids' => $decodeUsers((array) ($data['escalation3_users'] ?? [])),
        ]);
        $rule->organization_id = $organizationId;
        $rule->save();

        return redirect()->route('admin.notification-rules.index')
            ->with('success', __('notification.flash.rule_saved', ['event' => $eventEnum->label()]));
    }

    private function resolveEvent(string $event): NotificationEvent {
        return NotificationEvent::tryFrom($event) ?? abort(404);
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
