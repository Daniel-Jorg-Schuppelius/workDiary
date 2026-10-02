<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DictationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Media;

use App\Enums\Media\DictationStatus;
use App\Jobs\TranscribeDictationJob;
use App\Models\Media\Dictation;
use App\Models\Platform\{Organization, User};
use App\Services\Media\Contracts\DictationStructurer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sprachdiktat (MVP-1060): Aufnahme speichern, lokal mit Whisper
 * transkribieren, das Audio löschen und — wenn das KI-Modul gebunden ist — den
 * Text in die Felder des Kontexts gliedern ({@see DictationStructurer}). Ohne
 * KI bleibt es beim Transkript; das Formular füllt der Mensch, nie die
 * Maschine still.
 */
final class DictationService {
    private const DISK = 'local';

    public function __construct(
        private readonly SpeechTranscriber $transcriber,
        private readonly DictationStructurer $structurer,
    ) {}

    public function isAvailable(): bool {
        return $this->transcriber->isAvailable();
    }

    public function store(Organization $organization, User $user, UploadedFile $audio, string $context, string $locale): Dictation {
        $extension = strtolower($audio->getClientOriginalExtension() ?: 'webm');
        $path = $audio->storeAs('dictations/' . $organization->id, Str::uuid()->toString() . '.' . $extension, self::DISK);

        $dictation = Dictation::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $user->id,
            'context' => $context,
            'locale' => $locale,
            'audio_disk' => self::DISK,
            'audio_path' => $path !== false ? $path : null,
        ]);
        TranscribeDictationJob::dispatch($dictation->id);

        return $dictation;
    }

    public function process(Dictation $dictation): void {
        if ($dictation->status !== DictationStatus::Pending) {
            return;
        }
        if ($dictation->audio_path === null || ! Storage::disk((string) $dictation->audio_disk)->exists($dictation->audio_path)) {
            $this->fail($dictation, 'audio_missing');

            return;
        }
        $text = $this->transcriber->transcribe(
            Storage::disk((string) $dictation->audio_disk)->path($dictation->audio_path),
            $dictation->locale,
            'txt',
            (float) config('media.transcription.dictation_timeout', 300),
        );
        $text = trim((string) $text);
        $this->deleteAudio($dictation);
        if ($text === '') {
            $this->fail($dictation, 'no_speech');

            return;
        }

        $dictation->forceFill([
            'transcript' => $text,
            'structured' => $this->structure($dictation, $text),
            'status' => DictationStatus::Done->value,
        ])->save();
    }

    /** @return array<string, string>|null */
    private function structure(Dictation $dictation, string $text): ?array {
        $organization = Organization::query()->withoutGlobalScopes()->find($dictation->organization_id);

        return $organization === null ? null : $this->structurer->structure($organization, $text, Dictation::CONTEXTS[$dictation->context] ?? [], $dictation->locale);
    }

    private function fail(Dictation $dictation, string $reason): void {
        $this->deleteAudio($dictation);
        $dictation->forceFill(['status' => DictationStatus::Failed->value, 'failure' => $reason])->save();
    }

    private function deleteAudio(Dictation $dictation): void {
        if ($dictation->audio_path !== null) {
            Storage::disk((string) $dictation->audio_disk)->delete($dictation->audio_path);
        }
        $dictation->forceFill(['audio_path' => null])->save();
    }
}
