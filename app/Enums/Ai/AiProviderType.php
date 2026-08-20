<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiProviderType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Ai;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Geplante Provider-Adapter des KI-Fundaments (Feature 025, MVP-398;
 * Adapter-Implementierungen folgen in MVP-407–410). `Fake` existiert nur
 * für Tests/Demo und wird von der produktiven {@see \App\Services\Ai\AiProviderFactory}
 * nie aufgelöst. `OpenAiCompatible` kann lokal ODER Cloud sein — die
 * Einstufung trifft der Admin bewusst je Verbindung (`is_local`); alle
 * anderen Typen haben eine feste Default-Lokalität.
 */
enum AiProviderType: string implements HasLabel {
    use HasOptions;

    case Anthropic = 'anthropic';
    case OpenAi = 'openai';
    case Gemini = 'gemini';
    case AzureOpenAi = 'azure_openai';
    case OpenAiCompatible = 'openai_compatible';
    case Ollama = 'ollama';
    case DeepL = 'deepl';
    case AzureTranslator = 'azure_translator';
    case GoogleTranslate = 'google_translate';
    case LibreTranslate = 'libretranslate';
    case Fake = 'fake';

    public function label(): string {
        return (string) __('enums.ai.provider.' . $this->value);
    }

    /** Familie des Typs; null = beide Familien möglich (nur Fake). */
    public function family(): ?AiFamily {
        return match ($this) {
            self::DeepL,
            self::AzureTranslator,
            self::GoogleTranslate,
            self::LibreTranslate => AiFamily::Translation,
            self::Fake => null,
            default => AiFamily::Llm,
        };
    }

    /**
     * Braucht der Provider ein Modell/Deployment an der Verbindung? Die
     * LLM-Adapter fordern es beim ersten Aufruf ein
     * ({@see \App\Services\Ai\Providers\AbstractHttpAiProvider::requireModel()});
     * ohne Angabe scheitert schon der Prüflauf. Übersetzungsdienste kommen
     * ohne aus.
     */
    public function requiresModel(): bool {
        return $this->family() === AiFamily::Llm;
    }

    /** Default für `is_local` neuer Verbindungen (Sensibilitäts-Gate). */
    public function isLocalByDefault(): bool {
        return match ($this) {
            self::Ollama, self::LibreTranslate => true,
            default => false,
        };
    }

    /** Nur der generische Adapter darf die Lokalität frei einstufen. */
    public function allowsLocalOverride(): bool {
        return $this === self::OpenAiCompatible || $this === self::Fake;
    }

    /**
     * Hat der Typ einen produktiven Adapter? GoogleTranslate ist spätere
     * Ausbaustufe, Fake nur Test/Demo — die Factory wirft für beide.
     */
    public function isImplemented(): bool {
        return $this !== self::GoogleTranslate && $this !== self::Fake;
    }

    /**
     * Im Verbindungs-Dialog anbietbare Typen: nur implementierte; Fake
     * zusätzlich in testing/local (dort ersetzt der Test die Factory).
     *
     * @return list<self>
     */
    public static function selectable(): array {
        return array_values(array_filter(
            self::cases(),
            static fn (self $type): bool => $type->isImplemented()
                || ($type === self::Fake && app()->environment('testing', 'local')),
        ));
    }
}
