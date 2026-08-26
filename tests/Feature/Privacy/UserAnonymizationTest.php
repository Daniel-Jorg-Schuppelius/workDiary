<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserAnonymizationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Enums\Attendance\AttendanceStatus;
use App\Models\{Attendance, AuditLog, Organization, TimeEntry, User};
use App\Services\Privacy\UserAnonymizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Anonymisierung ausgeschiedener Mitarbeiter (Feature 130, MVP-694 — H21,
 * Folge-Punkt aus MVP-689): PII verschwindet (Stammdaten, Kontakt-Morphs,
 * CTI, Avatar), die Arbeitszeit-/Lohn-Nachweise bleiben am Datensatz
 * verknüpft; idempotent; Guards gegen aktive Konten.
 */
final class UserAnonymizationTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $actor;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->actor = User::factory()->admin()->create(['organization_id' => $this->org->id]);
    }

    private function offboardedMember(): User {
        $member = User::factory()->user()->create([
            'organization_id' => $this->org->id,
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'phone' => '+49 30 1234567',
            'mobile' => '+49 170 1234567',
            'tax_identification_number' => '12345678901',
            'social_security_number' => '12 123456 M 123',
            'date_of_birth' => '1980-05-04',
            'health_insurance' => 'Testkasse',
            'home_address' => 'Musterweg 1, 12345 Berlin',
            'deactivated_at' => now()->subYears(4),
            'left_at' => now()->subYears(4)->toDateString(),
        ]);
        $member->setCtiExtension('030 7654321');
        $member->save();

        $member->addresses()->create([
            'organization_id' => $this->org->id,
            'kind' => 'private',
            'street' => 'Musterweg 1',
            'zip' => '12345',
            'city' => 'Berlin',
            'country_code' => 'DE',
            'is_primary' => true,
        ]);
        $member->bankAccounts()->create([
            'organization_id' => $this->org->id,
            'account_holder' => 'Max Mustermann',
            'iban' => 'DE02120300000000202051',
            'bic' => 'BYLADEM1001',
            'is_primary' => true,
        ]);
        $member->attachments()->create([
            'organization_id' => $this->org->id,
            'disk' => 'local',
            'path' => 'avatars/max.png',
            'original_name' => 'max.png',
            'mime' => 'image/png',
            'size' => 10,
            'meta_type' => 'avatar',
        ]);

        return $member->refresh();
    }

    private function seedEvidence(User $member): void {
        Attendance::withoutEvents(function () use ($member): void {
            Attendance::query()->create([
                'organization_id' => $this->org->id,
                'user_id' => $member->id,
                'date' => now()->subYears(5)->toDateString(),
                'started_at' => now()->subYears(5)->setTime(8, 0),
                'ended_at' => now()->subYears(5)->setTime(16, 0),
                'duration_minutes' => 480,
                'status' => AttendanceStatus::Closed,
            ]);
        });
        TimeEntry::query()->create([
            'organization_id' => $this->org->id,
            'user_id' => $member->id,
            'date' => now()->subYears(5)->toDateString(),
            'minutes' => 480,
        ]);
    }

    public function test_anonymize_strips_pii_and_keeps_evidence_linked(): void {
        $member = $this->offboardedMember();
        $this->seedEvidence($member);

        app(UserAnonymizationService::class)->anonymize($member, $this->actor);
        $member->refresh();

        $this->assertNotNull($member->anonymized_at);
        $this->assertSame("Ausgeschiedene:r Mitarbeiter:in #{$member->id}", $member->name);
        $this->assertSame("anonymisiert-{$member->id}@example.invalid", $member->email);
        foreach (['first_name', 'last_name', 'phone', 'mobile', 'tax_identification_number', 'social_security_number', 'date_of_birth', 'health_insurance', 'home_address', 'cti_extension', 'cti_extension_hash'] as $field) {
            $this->assertNull($member->getAttribute($field), "PII-Feld nicht genullt: {$field}");
        }
        $this->assertSame(0, $member->addresses()->count(), 'Kontakt-Morph Adresse muss weg sein.');
        $this->assertSame(0, $member->bankAccounts()->count(), 'Kontakt-Morph Bankverbindung muss weg sein.');
        $this->assertNull($member->avatar(), 'Avatar muss weg sein.');

        // Nachweise (RETENTION_FK_TABLES) bleiben personengebunden verknüpft.
        $this->assertSame(1, Attendance::query()->where('user_id', $member->id)->count());
        $this->assertSame(1, TimeEntry::query()->where('user_id', $member->id)->count());

        // Austritts-Metadaten bleiben (Interpretation der Nachweise).
        $this->assertNotNull($member->left_at);
        $this->assertNotNull($member->deactivated_at);

        $this->assertSame(1, AuditLog::query()->where('event', 'user.anonymized')->count());
    }

    public function test_anonymize_is_idempotent(): void {
        $member = $this->offboardedMember();
        $service = app(UserAnonymizationService::class);

        $service->anonymize($member, $this->actor);
        $nameAfterFirst = $member->refresh()->name;

        $service->anonymize($member->refresh(), $this->actor);

        $this->assertSame($nameAfterFirst, $member->refresh()->name);
        $this->assertSame(1, AuditLog::query()->where('event', 'user.anonymized')->count(), 'Zweiter Aufruf darf kein Doppel-Audit erzeugen.');
    }

    public function test_anonymize_rejects_active_accounts(): void {
        $member = User::factory()->user()->create([
            'organization_id' => $this->org->id,
            'left_at' => now()->subYears(4)->toDateString(),
        ]);

        $this->expectException(RuntimeException::class);
        app(UserAnonymizationService::class)->anonymize($member, $this->actor);
    }

    public function test_anonymize_requires_a_leaving_date(): void {
        $member = User::factory()->user()->create([
            'organization_id' => $this->org->id,
            'deactivated_at' => now()->subYears(4),
        ]);

        $this->expectException(RuntimeException::class);
        app(UserAnonymizationService::class)->anonymize($member, $this->actor);
    }
}
