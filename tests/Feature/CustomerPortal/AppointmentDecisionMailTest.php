<?php
/*
 * Created on   : Tue Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppointmentDecisionMailTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\CustomerPortal;

use App\Mail\AppointmentDecisionMail;
use App\Models\{AppointmentRequest, BookableService, Customer, User};
use App\Services\Appointments\AppointmentRequestService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * Entscheidungs-Mail der Terminbuchung (Feature 087, Folgepunkt ICS):
 * Die Bestätigung trägt den Termin als Kalenderdatei, die Ablehnung
 * nennt den Grund — und ein Mail-Fehler rollt die Entscheidung nie zurück.
 */
final class AppointmentDecisionMailTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private Customer $customer;

    private User $portalUser;

    private User $admin;

    private BookableService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($this->customer);
        $this->portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->service = BookableService::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Wartungstermin',
            'duration_minutes' => 60,
            'lead_time_hours' => 24,
            'cancel_hours' => 24,
            'buffer_minutes' => 15,
            'active' => true,
        ]);
    }

    private function request(): AppointmentRequest {
        return app(AppointmentRequestService::class)->requestFromPortal(
            $this->service,
            $this->customer,
            $this->portalUser,
            CarbonImmutable::now()->addDays(3)->setTime(10, 0),
        );
    }

    public function test_confirmation_mails_the_invitee_with_an_ics_attachment(): void {
        Mail::fake();
        $request = $this->request();

        app(AppointmentRequestService::class)->confirm($request, $this->admin);

        Mail::assertSent(AppointmentDecisionMail::class, function (AppointmentDecisionMail $mail): bool {
            return $mail->hasTo($this->portalUser->email)
                && count($mail->attachments()) === 1;
        });
    }

    public function test_decline_mails_the_reason_without_an_attachment(): void {
        Mail::fake();
        $request = $this->request();

        app(AppointmentRequestService::class)->decline($request, $this->admin, 'Kein Techniker verfügbar.');

        Mail::assertSent(AppointmentDecisionMail::class, function (AppointmentDecisionMail $mail): bool {
            return $mail->hasTo($this->portalUser->email)
                && $mail->attachments() === [];
        });
    }

    /** Der Mail-Versand ist Beiwerk: Ein Transportfehler lässt die Entscheidung stehen. */
    public function test_mail_failure_does_not_roll_back_the_decision(): void {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        $request = $this->request();

        app(AppointmentRequestService::class)->confirm($request, $this->admin);

        $this->assertSame(AppointmentRequest::STATUS_CONFIRMED, $request->fresh()?->status);
    }
}
