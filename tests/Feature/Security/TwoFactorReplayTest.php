<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TwoFactorReplayTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\Auth\TwoFactorService;
use PragmaRX\Google2FAQRCode\Google2FA;
use Tests\TestCase;

/**
 * Regression zum Whitebox-Befund 2026-07 (MVP-099): ein TOTP-Code darf pro
 * Nutzer nur EINMAL gelten. Ein abgefangener Code lässt sich sonst innerhalb
 * seines ~90-Sekunden-Fensters an der Login-Challenge erneut abspielen.
 */
final class TwoFactorReplayTest extends TestCase {
    public function test_totp_code_is_accepted_only_once_per_user(): void {
        $service = app(TwoFactorService::class);
        $secret = $service->generateSecret();
        $code = (new Google2FA())->getCurrentOtp($secret);

        $user = new User();
        $user->id = 987654;

        // Erster Gebrauch gültig, direkter Replay desselben Codes abgelehnt.
        $this->assertTrue($service->verifyForUser($user, $secret, $code));
        $this->assertFalse($service->verifyForUser($user, $secret, $code));

        // Ein anderer Nutzer ist davon unberührt (eigener Zeitschritt-Schlüssel).
        $other = new User();
        $other->id = 123456;
        $this->assertTrue($service->verifyForUser($other, $secret, $code));
    }
}
