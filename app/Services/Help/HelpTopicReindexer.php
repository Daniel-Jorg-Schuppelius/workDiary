<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpTopicReindexer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Help;

use App\Models\HelpTopic;

class HelpTopicReindexer {
    public function __construct(
        private readonly HelpTopicLoader $loader,
    ) {}

    /**
     * Synchronisiert alle Topic-Markdown-Dateien in die DB.
     * Idempotent: gleiche Eingabe → gleicher DB-Zustand.
     *
     * @return array{upserted:int, deleted:int}
     */
    public function reindex(): array {
        $items = $this->loader->loadAll();

        $upserted = 0;
        $seenKeys = [];
        foreach ($items as $item) {
            $seenKeys[] = $item['topic'] . '|' . $item['locale'];

            HelpTopic::query()->updateOrCreate(
                ['topic' => $item['topic'], 'locale' => $item['locale']],
                [
                    'title' => $item['title'],
                    'audience' => $item['audience'],
                    'version' => $item['version'],
                    'body_md' => $item['body_md'],
                    'body_html' => $item['body_html'],
                    'related' => $item['related'],
                    'source_updated_at' => $item['source_updated_at'],
                ]
            );
            $upserted++;
        }

        $deleted = 0;
        $existing = HelpTopic::query()->get(['id', 'topic', 'locale']);
        foreach ($existing as $row) {
            $key = $row->topic . '|' . $row->locale;
            if (! in_array($key, $seenKeys, true)) {
                $row->delete();
                $deleted++;
            }
        }

        return ['upserted' => $upserted, 'deleted' => $deleted];
    }
}
