<?php
/*
 * Created on   : Sat Aug 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningTranslationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\LearningTranslationStatus;
use App\Models\Learning\{LearningContentTranslation, LearningCourse, LearningUnit};
use App\Models\User;
use App\Services\Ai\AiInvocationService;
use App\Services\Ai\Dto\{AiTranslationResult, TranslateRequest};
use App\Services\Ai\Exceptions\AiException;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kursinhalte übersetzen (Feature 149, MVP-748).
 *
 * **Eine Lesehilfe, kein zweiter Kurs.** Die Übersetzung hängt an derselben
 * Kursversion; es bleibt bei einer Freigabe und einem Nachweis.
 *
 * **Maßgeblich bleibt die Ausgangssprache.** Eine maschinell übersetzte
 * Sicherheitsunterweisung darf nicht unbesehen als Nachweis gelten —
 * deshalb ist eine frische Übersetzung immer `draft` und wird Lernenden
 * erst nach **Freigabe durch einen Menschen** gezeigt. Die KI schlägt vor,
 * sie entscheidet nichts (EU-KI-VO Anhang III Nr. 3).
 *
 * Übersetzt werden nur **Texte**: Titel, Untertitel, Überschriften,
 * Absätze, Hinweise und Checklisten. Medien, Einbettungen und die
 * Prüfungsfragen bleiben unberührt — eine übersetzte Prüfungsfrage wäre
 * eine andere Frage und würde die Prüfungsakte verfälschen.
 */
class LearningTranslationService {
    public const CAPABILITY = 'documents.item_translate';

    /** Blockarten mit übersetzbarem Text. */
    private const TRANSLATABLE_BLOCKS = ['heading', 'text', 'callout', 'checklist'];

    public function __construct(
        private readonly AiInvocationService $invocation,
    ) {}

    /**
     * Kurs samt Lerneinheiten in eine Zielsprache übersetzen.
     *
     * @return list<LearningContentTranslation>
     */
    public function translateCourse(LearningCourse $course, string $locale, ?int $connectionId = null): array {
        $this->guardLocale($course, $locale);

        $organization = $course->organization;

        if ($organization === null) {
            throw new AiException((string) __('learning.errors.translation_no_organization'));
        }

        $created = [];

        $created[] = $this->store($course, $locale, [
            'title' => $this->text($organization, (string) $course->title, $locale, $connectionId),
            'subtitle' => $course->subtitle !== null
                ? $this->text($organization, (string) $course->subtitle, $locale, $connectionId)
                : null,
        ]);

        foreach ($course->units()->orderBy('position')->get() as $unit) {
            $created[] = $this->store($unit, $locale, [
                'title' => $this->text($organization, (string) $unit->title, $locale, $connectionId),
                'blocks' => $this->translateBlocks($organization, $unit, $locale, $connectionId),
            ]);
        }

        return $created;
    }

    /**
     * Freigeben — erst jetzt sehen Lernende die Übersetzung.
     */
    public function approve(LearningContentTranslation $translation, User $actor, ?Carbon $now = null): LearningContentTranslation {
        $translation->forceFill([
            'status' => LearningTranslationStatus::Approved,
            'approved_by_user_id' => $actor->id,
            'approved_at' => $now ?? Carbon::now(),
        ])->save();

        return $translation->refresh();
    }

    /**
     * Anzuzeigende Felder in der Sprache der lernenden Person.
     *
     * Fällt auf die Ausgangssprache zurück, wenn keine freigegebene und
     * aktuelle Übersetzung vorliegt — lieber verständlich in der falschen
     * Sprache als falsch in der richtigen.
     *
     * @return array<string, mixed>|null
     */
    public function fieldsFor(Model $subject, string $locale): ?array {
        $translation = LearningContentTranslation::query()
            ->where('translatable_type', $subject->getMorphClass())
            ->where('translatable_id', $subject->getKey())
            ->where('locale', $locale)
            ->first();

        if ($translation === null || ! $translation->isUsableFor($this->sourceHash($subject))) {
            return null;
        }

        return $translation->fields();
    }

    /**
     * Prüfsumme des Ausgangsstands. Ändert sich der Stoff, ist jede
     * Übersetzung davon veraltet.
     */
    public function sourceHash(Model $subject): string {
        $payload = $subject instanceof LearningCourse
            ? [$subject->title, $subject->subtitle]
            : [$subject->getAttribute('title'), $subject->getAttribute('content')];

        return (string) CryptoHelper::hash(json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function translateBlocks(\App\Models\Organization $organization, LearningUnit $unit, string $locale, ?int $connectionId): array {
        $out = [];

        foreach ($unit->blocks() as $index => $block) {
            $type = (string) ($block['type'] ?? '');

            if (! in_array($type, self::TRANSLATABLE_BLOCKS, true)) {
                continue;
            }

            $entry = ['index' => $index, 'type' => $type];

            if (isset($block['text']) && trim((string) $block['text']) !== '') {
                $entry['text'] = $this->text($organization, (string) $block['text'], $locale, $connectionId);
            }

            if (isset($block['items']) && is_array($block['items'])) {
                $entry['items'] = array_map(
                    fn (mixed $item): string => $this->text($organization, (string) $item, $locale, $connectionId),
                    array_values($block['items'])
                );
            }

            $out[] = $entry;
        }

        return $out;
    }

    private function text(\App\Models\Organization $organization, string $source, string $locale, ?int $connectionId): string {
        if (trim($source) === '') {
            return $source;
        }

        $result = $this->invocation->invoke(
            $organization,
            self::CAPABILITY,
            new TranslateRequest(text: $source, targetLanguage: $locale, formality: 'more'),
            $connectionId,
        );

        $payload = $result->result;

        if (! $payload instanceof AiTranslationResult) {
            throw new AiException((string) __('ai.error.unexpected_result_type'));
        }

        return $payload->text;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function store(Model $subject, string $locale, array $fields): LearningContentTranslation {
        return DB::transaction(function () use ($subject, $locale, $fields): LearningContentTranslation {
            $translation = LearningContentTranslation::query()->firstOrNew([
                'translatable_type' => $subject->getMorphClass(),
                'translatable_id' => $subject->getKey(),
                'locale' => $locale,
            ]);

            // Eine neue Übersetzung ist IMMER Entwurf — auch wenn die alte
            // schon freigegeben war. Sonst ginge eine ungeprüfte maschinelle
            // Fassung als geprüft durch.
            $translation->fill([
                'organization_id' => (int) $subject->getAttribute('organization_id'),
                'payload' => (string) json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'source_hash' => $this->sourceHash($subject),
                'status' => LearningTranslationStatus::Draft,
                'approved_by_user_id' => null,
                'approved_at' => null,
            ])->save();

            return $translation->refresh();
        });
    }

    private function guardLocale(LearningCourse $course, string $locale): void {
        $available = (array) config('app.available_locales', ['de', 'en']);

        if (! in_array($locale, $available, true)) {
            throw ValidationException::withMessages([
                'locale' => (string) __('learning.errors.translation_locale_unknown'),
            ]);
        }

        // In die eigene Sprache zu übersetzen ergibt keinen Sinn und würde
        // den Ausgangstext durch eine Maschinenfassung ersetzen.
        $source = $course->organization->locale ?? config('app.locale');

        if ($locale === $source) {
            throw ValidationException::withMessages([
                'locale' => (string) __('learning.errors.translation_same_locale'),
            ]);
        }
    }
}
