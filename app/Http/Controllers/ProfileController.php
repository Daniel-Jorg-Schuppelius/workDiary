<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProfileController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Notification\NotificationEvent;
use App\Http\Controllers\Concerns\ManagesUserContactDetails;
use App\Models\{Attachment, User};
use App\Notifications\GenericEventNotification;
use App\Services\Attachments\ImageMetaUploader;
use App\Support\Auth\RecentAuthentication;
use CommonToolkit\Helper\Data\{EmailHelper, PhoneNumberHelper};
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request, UploadedFile};
use Illuminate\Support\Facades\{Hash, Log, Notification};
use Illuminate\Validation\{Rule, ValidationException};

class ProfileController extends Controller {
    use ManagesUserContactDetails;

    public function __construct(private readonly ImageMetaUploader $avatarUploader) {}

    public function edit(): View {
        /** @var User $user */
        $user = $this->authUser();
        $user->loadMissing(['attachments', 'addresses', 'bankAccounts']);

        return view('account._profile_dialog', [
            'user' => $user,
            'isDialog' => true,
        ]);
    }

    public function update(Request $request): RedirectResponse {
        /** @var User $user */
        $user = $this->authUser();

        $themeKeys = app(\App\Services\ThemeService::class)->allowedKeys();
        $avatarMaxKb = (int) config('branding.limits.avatar_kb', 1024);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'preferences' => ['sometimes', 'array'],
            'preferences.theme' => ['nullable', 'string', Rule::in($themeKeys)],
            'preferences.locale' => ['nullable', Rule::in(\App\Support\Locales::enabledCodes())],
            'preferences.timezone' => ['nullable', 'timezone'],
            'preferences.date_format' => ['nullable', Rule::in(\App\Support\Formats::dateOptions())],
            'preferences.time_format' => ['nullable', Rule::in(\App\Support\Formats::timeOptions())],
            'preferences.startpage' => ['nullable', 'string', Rule::in(\App\Services\Navigation\StartPageResolver::routeOptions())],
            // Benachrichtigungs-Präferenzen (MVP-018): Mail global an/aus, Ruhezeit nur für Mail/Push — In-App sammelt immer.
            'preferences.notifications' => ['sometimes', 'array'],
            'preferences.notifications.mail_enabled' => ['nullable', 'boolean'],
            'preferences.notifications.quiet_from' => ['nullable', 'date_format:H:i'],
            'preferences.notifications.quiet_to' => ['nullable', 'date_format:H:i'],
            'avatar' => ['nullable', 'file'],
            'remove_avatar' => ['nullable', 'boolean'],
            // CTI-Anrufer-Pop-up (MVP-118): eigene Durchwahl als Opt-in.
            'cti_extension' => ['nullable', 'string', 'max:40', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && trim($value) !== '' && PhoneNumberHelper::toE164(trim($value), 'DE') === null) {
                    $fail((string) __('cti.profile.invalid'));
                }
            }],
        ] + $this->contactDetailRules());

        // Der E-Mail-Wechsel verlegt Passwort-Reset und Mail-Codes auf die neue
        // Adresse — aus kurzem Sitzungszugriff würde sonst eine dauerhafte
        // Kontoübernahme (Sicherheitsaudit 2026-09-17, authflow-1). Daher nur
        // mit frischer Anmeldung oder aktuellem Passwort, und die alte Adresse
        // erfährt davon.
        $previousEmail = (string) $user->email;
        $emailChanged = EmailHelper::normalize((string) $data['email']) !== EmailHelper::normalize($previousEmail);
        if ($emailChanged) {
            $this->assertMayChangeEmail($request, $user);
        }

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);
        $this->fillUserContactFields($user, $data);

        // Durchwahl (Opt-in) verschlüsselt + Hash setzen; leere Eingabe hebt das Opt-in auf. Nur bei Formular-Feld berühren.
        if ($request->has('cti_extension')) {
            $user->setCtiExtension(is_string($data['cti_extension'] ?? null) ? $data['cti_extension'] : null);
        }

        if ($request->has('preferences')) {
            $submitted = array_filter(
                (array) ($data['preferences'] ?? []),
                static fn($v) => $v !== null && $v !== ''
            );
            // Vom Profilformular verwaltete Keys ersetzt die Eingabe (bzw. leert sie); andere Keys — v. a.
            // color_scheme vom Header-Umschalter — bleiben, damit Speichern die Hell/Dunkel-Wahl nicht zurücksetzt.
            $existing = (array) ($user->preferences ?? []);
            $formKeys = ['theme', 'locale', 'timezone', 'date_format', 'time_format', 'startpage', 'notifications'];
            $preserved = array_diff_key($existing, array_flip($formKeys));
            // Innerhalb von `notifications` gibt es Schlüssel, die das Formular
            // NICHT kennt: das SMS-Opt-in (Feature 147) entsteht über den
            // Bestätigungscode-Weg. Ohne diese Rettung würde jedes Speichern des
            // Profils eine erteilte Einwilligung stillschweigend löschen.
            $keptNotifications = array_intersect_key(
                (array) ($existing['notifications'] ?? []),
                array_flip(['sms_opt_in', 'sms_number_hash', 'sms_verified_at']),
            );
            if ($keptNotifications !== []) {
                $submitted['notifications'] = array_merge($keptNotifications, (array) ($submitted['notifications'] ?? []));
            }
            $merged = array_merge($preserved, $submitted);
            $user->preferences = $merged === [] ? null : $merged;
        }

        $user->save();

        $this->syncUserAddress($user, (array) ($data['address'] ?? []));
        $this->syncUserBankAccount($user, (array) ($data['bank'] ?? []));

        $avatar = $request->file('avatar');
        if ($avatar instanceof UploadedFile) {
            $this->avatarUploader->replace($user, Attachment::META_AVATAR, $avatar, $avatarMaxKb, 'avatar');
        } elseif ($request->boolean('remove_avatar')) {
            $this->avatarUploader->delete($user, Attachment::META_AVATAR);
        }

        if ($emailChanged) {
            $this->notifyPreviousAddress($user, $previousEmail);
        }

        return back()->with('success', __('Profil aktualisiert.'));
    }

    /**
     * Frische Anmeldung (≤ auth.password_timeout) oder Feld `current_password`.
     * SSO-pflichtige Konten ohne nutzbares Passwort melden sich dafür erneut an.
     */
    private function assertMayChangeEmail(Request $request, User $user): void {
        if (RecentAuthentication::isRecent($request)) {
            return;
        }

        $current = (string) $request->input('current_password', '');
        if ($current !== '' && Hash::check($current, (string) $user->password)) {
            RecentAuthentication::confirm($request);

            return;
        }

        throw ValidationException::withMessages([
            'current_password' => __('Bitte bestätigen Sie die Änderung der E-Mail-Adresse mit Ihrem aktuellen Passwort.'),
        ]);
    }

    /** Hinweis an die bisherige Adresse — dort fällt eine Übernahme zuerst auf. */
    private function notifyPreviousAddress(User $user, string $previousEmail): void {
        if ($previousEmail === '') {
            return;
        }

        $params = ['email' => (string) $user->email];

        try {
            Notification::route('mail', $previousEmail)->notify(new GenericEventNotification(
                NotificationEvent::SecurityNewDevice,
                [
                    'title' => (string) __('notification.message.email_changed_title'),
                    'title_key' => 'notification.message.email_changed_title',
                    'message' => (string) __('notification.message.email_changed_message', $params),
                    'message_key' => 'notification.message.email_changed_message',
                    'message_params' => $params,
                    'url' => route('account.password.edit'),
                ],
                ['mail'],
            ));
        } catch (\Throwable $e) {
            // Der Hinweis darf die Änderung nie verhindern.
            Log::warning('profile.email_change_notice_failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Persistiert die Wahl des Header-Umschalters (leichtgewichtig, JSON).
     * Der Header setzt nur den Farbmodus ('light'|'dark'|'auto') — WELCHES Theme
     * das ist, kommt aus dem Org-Hell/Dunkel-Paar (ThemeService). Ein zuvor
     * gesetztes konkretes Profil-Theme wird dabei aufgehoben (der Modus gewinnt).
     */
    public function updateTheme(Request $request): \Illuminate\Http\JsonResponse {
        /** @var User $user */
        $user = $this->authUser();

        $data = $request->validate([
            'scheme' => ['required', 'in:auto,light,dark'],
        ]);

        $prefs = (array) ($user->preferences ?? []);
        unset($prefs['theme']); // konkretes Theme weicht der Modus-Wahl
        if (($data['scheme'] ?? 'auto') === 'auto') {
            unset($prefs['color_scheme']);
        } else {
            $prefs['color_scheme'] = $data['scheme'];
        }
        $user->preferences = $prefs === [] ? null : $prefs;
        $user->save();

        return response()->json(['ok' => true, 'scheme' => $data['scheme']]);
    }
}
