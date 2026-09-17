<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportedDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Collections\Import;

use Carbon\CarbonInterface;

/**
 * Ein zu übernehmender Inhalt aus Obsidian oder OneNote (MVP-815), schon als
 * Text aufbereitet.
 */
final readonly class ImportedDocument {
    /**
     * @param  list<string>  $folders  Ordner bzw. Notizbuch-Abschnitte, oberste zuerst — werden zu Sammlungen
     * @param  list<string>  $tags
     * @param  list<string>  $names  Namen, unter denen andere Dokumente auf dieses verweisen ([[Wikilink]])
     * @param  list<string>  $linkNames  Verweise dieses Dokuments auf andere
     */
    public function __construct(
        public string $externalId,
        public string $title,
        public string $text,
        public array $folders = [],
        public array $tags = [],
        public array $names = [],
        public array $linkNames = [],
        public ?CarbonInterface $modifiedAt = null,
        public string $sourceLabel = '',
    ) {}
}
