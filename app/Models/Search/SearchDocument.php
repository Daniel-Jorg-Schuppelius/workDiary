<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Search;

use App\Enums\Search\SearchSourceType;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Abgeleitetes Suchdokument einer Tätigkeitsquelle (Feature 153, MVP-770).
 * Geschrieben ausschließlich vom {@see \App\Services\Search\Indexing\SearchIndexer};
 * die Quelle bleibt führend, `search:rebuild` baut alles neu auf.
 *
 * @property int $id
 * @property int $organization_id
 * @property SearchSourceType $source_type
 * @property int $source_id
 * @property Carbon|null $occurred_at
 * @property bool $date_only
 * @property int|null $user_id
 * @property int|null $assigned_user_id
 * @property int|null $customer_id
 * @property int|null $foreign_customer_id
 * @property int|null $project_id
 * @property int|null $minutes
 * @property bool $restricted
 * @property string $title
 * @property string|null $excerpt
 * @property string $search_text
 * @property Carbon|null $source_updated_at
 */
class SearchDocument extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'source_type',
        'source_id',
        'occurred_at',
        'date_only',
        'user_id',
        'assigned_user_id',
        'customer_id',
        'foreign_customer_id',
        'project_id',
        'minutes',
        'restricted',
        'title',
        'excerpt',
        'search_text',
        'source_updated_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'source_type' => SearchSourceType::class,
        'source_id' => 'integer',
        'occurred_at' => 'datetime',
        'date_only' => 'boolean',
        'user_id' => 'integer',
        'assigned_user_id' => 'integer',
        'customer_id' => 'integer',
        'foreign_customer_id' => 'integer',
        'project_id' => 'integer',
        'minutes' => 'integer',
        'restricted' => 'boolean',
        'source_updated_at' => 'datetime',
    ];
}
