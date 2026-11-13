<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpdateLoginPolicyRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\Auth\LoginPolicyMode;
use App\Enums\User\{Permission, UserRole};
use App\Http\Requests\BaseFormRequest;
use App\Models\Platform\User;
use App\Services\Auth\LoginPolicy\{LoginHours, LoginNetworks, LoginPolicyEvaluator};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\{Rule, Validator};

/**
 * Anmelderichtlinie speichern (Feature 205, MVP-1654–1656). Die Netze kommen
 * als Text mit einem Eintrag je Zeile; Zeitfenster als Wochentag => from/until.
 */
class UpdateLoginPolicyRequest extends BaseFormRequest {
    public function authorize(): bool {
        return Gate::allows(Permission::AuthPolicyManage->value);
    }

    /**
     * Rollen, für die Zeitfenster gelten können; Org-Admins sind von Zeit und
     * Netz ohnehin ausgenommen, Portal-Konten haben eigene Regeln.
     *
     * @return list<string>
     */
    public static function hourRoleOptions(): array {
        return array_values(array_diff(UserRole::values(), [UserRole::Admin->value, UserRole::Kunde->value]));
    }

    /**
     * Rollen, für die eine Passkey-Pflicht gelten kann — auch Org-Admins.
     *
     * @return list<string>
     */
    public static function passkeyRoleOptions(): array {
        return array_values(array_diff(UserRole::values(), [UserRole::Kunde->value]));
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'hours_mode' => ['required', Rule::enum(LoginPolicyMode::class)],
            'hours' => ['nullable', 'array'],
            'hours.*' => ['nullable', 'array'],
            'hours.*.from' => ['nullable', 'string', 'max:5'],
            'hours.*.until' => ['nullable', 'string', 'max:5'],
            'hours_roles' => ['nullable', 'array'],
            'hours_roles.*' => ['string', Rule::in(self::hourRoleOptions())],
            'network_mode' => ['required', Rule::enum(LoginPolicyMode::class)],
            'networks' => ['nullable', 'string', 'max:5000'],
            'passkey_roles' => ['nullable', 'array'],
            'passkey_roles.*' => ['string', Rule::in(self::passkeyRoleOptions())],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('hours', []) as $day => $window) {
                    if (! is_array($window) || ! in_array((int) $day, range(1, 7), true)) {
                        $validator->errors()->add('hours', __('login_policy.validation.hours_day'));

                        continue;
                    }
                    $from = $window['from'] ?? null;
                    $until = $window['until'] ?? null;
                    if (blank($from) && blank($until)) {
                        continue;
                    }
                    if (! LoginHours::isValidTime($from) || ! LoginHours::isValidTime($until) || $from === LoginHours::END_OF_DAY || $from === $until) {
                        $validator->errors()->add('hours.' . $day, __('login_policy.validation.hours_window'));
                    }
                }

                // Kein Selbstaussperren: wer eine Passkey-Pflicht für die eigene
                // Rolle einschaltet, hat selbst schon einen Passkey.
                $actor = $this->user();
                $passkeyRoles = array_map('strval', (array) $this->input('passkey_roles', []));
                if ($actor instanceof User && $passkeyRoles !== []) {
                    $evaluator = app(LoginPolicyEvaluator::class);
                    if (array_intersect($evaluator->subject($actor)['roles'], $passkeyRoles) !== [] && ! $evaluator->hasPasskey($actor)) {
                        $validator->errors()->add('passkey_roles', __('login_policy.validation.passkey_self'));
                    }
                }

                $entries = $this->networkEntries();
                if (count($entries) > LoginNetworks::MAX_ENTRIES) {
                    $validator->errors()->add('networks', __('login_policy.validation.networks_max', ['max' => LoginNetworks::MAX_ENTRIES]));
                }
                foreach ($entries as $entry) {
                    if (! LoginNetworks::isValidEntry($entry)) {
                        $validator->errors()->add('networks', __('login_policy.validation.network_entry', ['entry' => mb_substr($entry, 0, 80)]));
                    }
                }
                if (count(array_filter($entries, LoginNetworks::isHostname(...))) > LoginNetworks::MAX_HOSTNAMES) {
                    $validator->errors()->add('networks', __('login_policy.validation.hostnames_max', ['max' => LoginNetworks::MAX_HOSTNAMES]));
                }

                // „Durchsetzen“ ohne ein einziges Fenster bzw. Netz sperrte alle
                // außer den Org-Admins aus — das ist ein Notaus, keine Richtlinie.
                if ($this->input('hours_mode') === LoginPolicyMode::Enforce->value && LoginHours::normalize((array) $this->input('hours', [])) === []) {
                    $validator->errors()->add('hours', __('login_policy.validation.hours_enforce_empty'));
                }
                if ($this->input('network_mode') === LoginPolicyMode::Enforce->value && $entries === []) {
                    $validator->errors()->add('networks', __('login_policy.validation.networks_enforce_empty'));
                }
            },
        ];
    }

    /**
     * Netze aus dem Textfeld: ein Eintrag je Zeile (Komma oder Leerzeichen
     * trennen ebenfalls), vereinheitlicht und ohne Doppelte.
     *
     * @return list<string>
     */
    public function networkEntries(): array {
        $parts = preg_split('/[\s,;]+/', (string) $this->input('networks', '')) ?: [];
        $entries = [];
        foreach ($parts as $part) {
            $entry = LoginNetworks::normalizeEntry($part);
            if ($entry !== '' && ! in_array($entry, $entries, true)) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }
}
