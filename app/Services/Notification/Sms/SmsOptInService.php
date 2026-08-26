<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SmsOptInService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\Sms;

use App\Enums\Notification\SmsDeliveryStatus;
use App\Models\{Organization, User};
use App\Plugins\Contracts\SmsProvider;
use App\Support\PhoneSearchKey;
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Opt-in je Mitarbeitendem für den SMS-Kanal (Feature 147, MVP-730).
 *
 * **Ohne Opt-in kein Versand — und ohne Bestätigungscode kein Opt-in.**
 * Eine Rufnummer aus dem Stammdatensatz allein reicht nicht: sie kann falsch
 * getippt oder veraltet sein, und dann geht eine Krisenalarmierung an einen
 * Fremden. Deshalb bindet das Opt-in an den SHA-256 der bestätigten
 * E.164-Nummer; ändert jemand später seine Mobilnummer, passt der Hash nicht
 * mehr und das Opt-in gilt automatisch als erloschen (statt still an die
 * alte Nummer weiterzusenden).
 *
 * Die Nummer selbst wird hier NICHT zusätzlich gespeichert — sie steht bereits
 * an `users.mobile`; die Präferenz trägt nur den Hash (Datenminimierung).
 */
class SmsOptInService {
    /** Gültigkeit des Bestätigungscodes. */
    public const CODE_TTL_MINUTES = 10;

    /** Präferenz-Bag, in der auch mail_enabled/push_enabled/Ruhezeit liegen. */
    private const BAG = 'notifications';

    public function __construct(private readonly SmsProviderResolver $providers) {}

    /** Bestätigte Mobilnummer in E.164 — oder null (kein Versand). */
    public function verifiedNumberFor(User $user): ?string {
        $number = PhoneSearchKey::of($user->mobile);
        if ($number === null) {
            return null;
        }

        $prefs = $this->bag($user);
        if (! filter_var($prefs['sms_opt_in'] ?? false, FILTER_VALIDATE_BOOL)) {
            return null;
        }

        $stored = (string) ($prefs['sms_number_hash'] ?? '');

        return $stored !== '' && hash_equals($stored, $this->hash($number)) ? $number : null;
    }

    public function hasOptIn(User $user): bool {
        return $this->verifiedNumberFor($user) !== null;
    }

    /**
     * Schickt einen Bestätigungscode an die hinterlegte Mobilnummer.
     *
     * Der Versand läuft bewusst am Opt-in-Guard vorbei (das Opt-in entsteht
     * ja erst) — dafür ausschließlich an die eigene Nummer der Person und mit
     * einem Text ohne jeden Fachbezug.
     *
     * @return string die Nummer, an die der Code ging (E.164)
     *
     * @throws RuntimeException wenn keine Nummer oder kein Gateway vorhanden ist
     */
    public function startVerification(User $user): string {
        $number = PhoneSearchKey::of($user->mobile);
        if ($number === null) {
            throw new RuntimeException((string) __('Es ist keine gültige Mobilnummer hinterlegt.'));
        }

        $organization = $user->organization;
        $provider = $organization instanceof Organization ? $this->providers->forOrganization($organization) : null;
        if (! $organization instanceof Organization || ! $provider instanceof SmsProvider) {
            throw new RuntimeException((string) __('Für diese Organisation ist kein SMS-Gateway aktiviert.'));
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->cacheKey($user), [
            'hash' => $this->hash($code),
            'number' => $this->hash($number),
        ], now()->addMinutes(self::CODE_TTL_MINUTES));

        $result = $provider->sendSms(
            $organization,
            $number,
            SmsText::shorten((string) __('sms.verification_code', ['code' => $code])),
            'verify-' . $user->id,
        );

        if ($result->status !== SmsDeliveryStatus::Sent) {
            Cache::forget($this->cacheKey($user));

            throw new RuntimeException((string) __('Der Bestätigungscode konnte nicht versendet werden.'));
        }

        // Audit ohne Inhalt und ohne Rufnummer (Feature 147).
        $user->audit('sms.verification_started', ['provider' => $provider->smsProviderId()]);

        return $number;
    }

    /** Prüft den Code und schaltet das Opt-in frei. */
    public function confirm(User $user, string $code): bool {
        $pending = Cache::get($this->cacheKey($user));
        $number = PhoneSearchKey::of($user->mobile);
        if (! is_array($pending) || $number === null) {
            return false;
        }

        // Nummer zwischen Anforderung und Bestätigung geändert → ungültig.
        if (! hash_equals((string) ($pending['number'] ?? ''), $this->hash($number))
            || ! hash_equals((string) ($pending['hash'] ?? ''), $this->hash(trim($code)))) {
            return false;
        }

        Cache::forget($this->cacheKey($user));
        $this->writeBag($user, [
            'sms_opt_in' => true,
            'sms_number_hash' => $this->hash($number),
            'sms_verified_at' => Carbon::now()->toIso8601String(),
        ]);
        $user->audit('sms.opt_in');

        return true;
    }

    /** Widerruf — jederzeit und ohne Bedingung (Art. 7 Abs. 3 DSGVO). */
    public function revoke(User $user): void {
        if (! filter_var($this->bag($user)['sms_opt_in'] ?? false, FILTER_VALIDATE_BOOL)) {
            return;
        }

        $this->writeBag($user, [
            'sms_opt_in' => false,
            'sms_number_hash' => null,
            'sms_verified_at' => null,
        ]);
        $user->audit('sms.opt_out');
    }

    /** @return array<string, mixed> */
    private function bag(User $user): array {
        return (array) $user->getPreference(self::BAG, []);
    }

    /** @param  array<string, mixed>  $values */
    private function writeBag(User $user, array $values): void {
        $user->setPreference(self::BAG, array_merge($this->bag($user), $values));
    }

    private function hash(string $value): string {
        return (string) CryptoHelper::hash($value, HashAlgorithm::SHA256);
    }

    private function cacheKey(User $user): string {
        return 'sms-verify:' . $user->id;
    }
}
