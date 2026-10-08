<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpTopic.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Platform;

use App\Models\Concerns\HasSqid;
use App\Services\Search\SearchTextNormalizer;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $topic
 * @property string $locale
 * @property string $title
 * @property list<string>|null $keywords
 * @property array<int,string>|null $audience
 * @property array<int,string>|null $modules
 * @property int $version
 * @property string $body_md
 * @property string $body_html
 * @property array<int,string>|null $related
 * @property array<int,array{level:int, text:string, anchor:string}>|null $headings
 * @property string|null $search_text
 * @property \Illuminate\Support\Carbon|null $source_updated_at
 */
class HelpTopic extends Model {
    use HasSqid;

    /** Obergrenze für `search_text` (TEXT-Spalte). */
    private const SEARCH_TEXT_LIMIT = 60000;

    protected $table = 'help_topics';

    protected $fillable = [
        'topic',
        'locale',
        'title',
        'keywords',
        'audience',
        'modules',
        'version',
        'body_md',
        'body_html',
        'related',
        'headings',
        'source_updated_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'keywords' => 'array',
        'audience' => 'array',
        'modules' => 'array',
        'related' => 'array',
        'headings' => 'array',
        'source_updated_at' => 'datetime',
    ];

    protected static function booted(): void {
        // Suchwörter folgen dem Inhalt bei jedem Speichern, beim Reindex wie bei direkt angelegten Zeilen (MVP-1079).
        static::saving(static function (HelpTopic $topic): void {
            $topic->search_text = $topic->buildSearchText();
        });
    }

    private function buildSearchText(): string {
        $normalizer = app(SearchTextNormalizer::class);
        $parts = [
            (string) $this->title,
            ...array_map('strval', $this->keywords ?? []),
            ...array_map(static fn(array $heading): string => (string) $heading['text'], $this->headings ?? []),
            // Link- und Bildziele (`](media/…)`) sind keine Suchwörter.
            (string) preg_replace('/\]\([^)]*\)/', ']', (string) $this->body_md),
        ];

        $tokens = [];
        foreach ($parts as $part) {
            foreach ($normalizer->tokens($part) as $token) {
                $tokens[$token] = true;
            }
        }

        return $normalizer->encode(array_map('strval', array_keys($tokens)), self::SEARCH_TEXT_LIMIT);
    }
}
