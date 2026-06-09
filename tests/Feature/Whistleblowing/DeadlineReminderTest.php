<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeadlineReminderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Models\{Organization, User};
use App\Models\Whistleblowing\{CaseAssignment, WhistleblowingCase};
use App\Notifications\Whistleblowing\WhistleblowingDeadlineNotification;
use App\Services\Whistleblowing\ReporterCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DeadlineReminderTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('whistleblowing.key', base64_encode(random_bytes(32)));
        config()->set('whistleblowing.lookup_key', base64_encode(random_bytes(32)));
    }

    public function test_overdue_case_notifies_assigned_handlers_content_free(): void {
        Notification::fake();

        $org = Organization::factory()->create();
        $handler = User::factory()->create(['organization_id' => $org->id]);
        $cred = app(ReporterCredentialService::class);
        $secret = $cred->generateSecret();

        $case = new WhistleblowingCase;
        $case->organization_id = $org->id;
        $case->initializeDek();
        $case->reporter_mode = 'anonymous';
        $case->category = 'fraud';
        $case->subject_ciphertext = 'GeheimerBetreff';
        $case->description_ciphertext = 'Beschreibung';
        $case->forceFill([
            'case_number' => $cred->generateCaseNumber(),
            'access_code_hash' => $cred->hashSecret($secret),
            'access_code_lookup' => $cred->lookupHmac($secret),
            'acknowledgement_due_at' => now()->subDay(), // ueberfaellig
        ]);
        $case->save();

        CaseAssignment::create([
            'organization_id' => $org->id, 'case_id' => $case->id, 'user_id' => $handler->id,
            'role' => 'processor', 'assigned_at' => now(),
        ]);

        // Zweimal laufen → idempotent: genau eine Benachrichtigung.
        $this->artisan('whistleblowing:deadlines')->assertExitCode(0);
        $this->artisan('whistleblowing:deadlines')->assertExitCode(0);

        Notification::assertSentToTimes($handler, WhistleblowingDeadlineNotification::class, 1);

        Notification::assertSentTo($handler, WhistleblowingDeadlineNotification::class, function ($notification) use ($case) {
            // Inhaltsarm: nur Fallnummer/Prioritaet/Art – kein Meldeinhalt.
            $data = $notification->toArray($notification);
            $this->assertSame($case->case_number, $data['case_number']);
            $this->assertArrayNotHasKey('subject', $data);
            $this->assertStringNotContainsString('GeheimerBetreff', json_encode($data));

            return true;
        });
    }

    public function test_acknowledged_case_is_not_reminded(): void {
        Notification::fake();

        $org = Organization::factory()->create();
        $cred = app(ReporterCredentialService::class);
        $secret = $cred->generateSecret();

        $case = new WhistleblowingCase;
        $case->organization_id = $org->id;
        $case->initializeDek();
        $case->reporter_mode = 'anonymous';
        $case->category = 'fraud';
        $case->subject_ciphertext = 'S';
        $case->description_ciphertext = 'D';
        $case->forceFill([
            'case_number' => $cred->generateCaseNumber(),
            'access_code_hash' => $cred->hashSecret($secret),
            'access_code_lookup' => $cred->lookupHmac($secret),
            'acknowledgement_due_at' => now()->subDay(),
            'acknowledged_at' => now()->subHours(2),
            'status' => 'acknowledged',
        ]);
        $case->save();

        $this->artisan('whistleblowing:deadlines')->assertExitCode(0);

        Notification::assertNothingSent();
    }
}
