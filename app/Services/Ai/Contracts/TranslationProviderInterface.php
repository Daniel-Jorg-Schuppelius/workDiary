<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TranslationProviderInterface.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

/**
 * Familien-Vertrag Übersetzung (Feature 025, MVP-398): dedizierte
 * MT-Dienste mit deterministischer Glossar-Erzwingung. Adapter
 * (MVP-409/410): DeepL, Azure Translator, LibreTranslate,
 * Google Cloud Translation.
 */
interface TranslationProviderInterface extends AiProviderInterface, TranslatesTextInterface {}
