<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DictationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Media;

use App\Enums\Media\DictationStatus;
use App\Models\Media\Dictation;
use App\Models\Platform\{Organization, User};
use App\Services\Media\Contracts\{DictationStructurer, NullDictationStructurer};
use App\Services\Media\Retention\MediaRetentionPolicies;
use App\Services\Media\SpeechTranscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\ComponentAttributeBag;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1060: Diktat — Whisper lokal, Audio weg nach der Transkription, Gliederung nur über das KI-Modul. */
class DictationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->setUpOrganization(['name' => 'Diktat Test']);
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->user);
    }

    private function transcriber(bool $available, ?string $text = null): void {
        $this->partialMock(SpeechTranscriber::class, function ($mock) use ($available, $text): void {
            $mock->shouldReceive('isAvailable')->andReturn($available);
            $mock->shouldReceive('transcribe')->andReturn($text);
        });
    }

    private function audio(): UploadedFile {
        return UploadedFile::fake()->create('diktat.webm', 12, 'audio/webm');
    }

    public function test_without_whisper_there_is_no_button_and_no_upload(): void {
        $this->transcriber(false);

        $this->post(route('dictations.store'), ['audio' => $this->audio(), 'context' => 'diary'])->assertStatus(503);
        $this->assertStringNotContainsString('data-dictation', view('components.dictation-button', ['target' => '#x', 'context' => 'diary', 'attributes' => new ComponentAttributeBag()])->render());
    }

    public function test_dictation_is_transcribed_structured_and_the_audio_deleted(): void {
        $this->transcriber(true, "Heizung entlüftet.\nThermostat im Bad defekt.");
        $this->app->instance(DictationStructurer::class, new class implements DictationStructurer {
            public function structure(Organization $organization, string $text, array $fields, string $locale): ?array {
                return ['work_done' => 'Heizung entlüftet', 'defects' => 'Thermostat im Bad defekt'];
            }
        });

        $this->get(route('diary.create'))->assertOk()->assertSee('data-dictation-target="#diary-content"', false);
        $id = $this->post(route('dictations.store'), ['audio' => $this->audio(), 'context' => 'diary'])->assertStatus(202)->json('id');

        $dictation = Dictation::query()->firstOrFail();
        $this->assertSame(DictationStatus::Done, $dictation->status);
        $this->assertNull($dictation->audio_path);
        $this->assertSame([], Storage::disk('local')->allFiles('dictations'));
        $this->get(route('dictations.show', $id))->assertOk()
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('transcript', "Heizung entlüftet.\nThermostat im Bad defekt.")
            ->assertJsonPath('fields.defects', 'Thermostat im Bad defekt');

        $other = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($other)->get(route('dictations.show', $id))->assertNotFound();
    }

    public function test_without_ai_the_transcript_stays_and_silence_fails(): void {
        $this->transcriber(true, '   ');
        $this->post(route('dictations.store'), ['audio' => $this->audio(), 'context' => 'protocol'])->assertStatus(202);
        $this->assertSame(DictationStatus::Failed, Dictation::query()->firstOrFail()->status);
        $this->assertSame('no_speech', Dictation::query()->firstOrFail()->failure);

        $this->app->instance(DictationStructurer::class, new NullDictationStructurer());
        $this->transcriber(true, 'Wand gespachtelt.');
        $id = $this->post(route('dictations.store'), ['audio' => $this->audio(), 'context' => 'protocol'])->json('id');
        $this->get(route('dictations.show', $id))->assertJsonPath('transcript', 'Wand gespachtelt.')->assertJsonPath('fields', []);
    }

    public function test_retention_removes_old_dictations(): void {
        $old = Dictation::query()->create(['organization_id' => $this->organization->id, 'created_by' => $this->user->id, 'context' => 'diary', 'locale' => 'de', 'status' => 'done', 'transcript' => 'alt']);
        $old->forceFill(['created_at' => now()->subYears(2)])->save();
        Dictation::query()->create(['organization_id' => $this->organization->id, 'created_by' => $this->user->id, 'context' => 'diary', 'locale' => 'de', 'status' => 'done', 'transcript' => 'neu']);

        $policy = (new MediaRetentionPolicies())->policies()[0];
        $overdue = ($policy->overdueQuery)($this->organization, now()->subYear())->get();
        $this->assertSame([$old->id], $overdue->pluck('id')->all());
        ($policy->purge)($overdue->first());
        $this->assertSame(1, Dictation::query()->count());
    }
}
