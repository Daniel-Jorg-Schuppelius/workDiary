<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpTopic.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $topic
 * @property string $locale
 * @property string $title
 * @property array<int,string>|null $audience
 * @property array<int,string>|null $modules
 * @property int $version
 * @property string $body_md
 * @property string $body_html
 * @property array<int,string>|null $related
 * @property array<int,array{level:int, text:string, anchor:string}>|null $headings
 * @property \Illuminate\Support\Carbon|null $source_updated_at
 */
class HelpTopic extends Model {
    use HasSqid;

    protected $table = 'help_topics';

    protected $fillable = [
        'topic',
        'locale',
        'title',
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
        'audience' => 'array',
        'modules' => 'array',
        'related' => 'array',
        'headings' => 'array',
        'source_updated_at' => 'datetime',
    ];
}
