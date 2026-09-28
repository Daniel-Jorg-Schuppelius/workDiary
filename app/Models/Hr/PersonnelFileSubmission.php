<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersonnelFileSubmission.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Hr;

use App\Enums\Hr\{HrDocumentCategory, PersonnelFileSubmissionStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Document\Document;
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Von der betroffenen Person eingereichte Unterlage (MVP-987). Die Datei liegt
 * bis zur Entscheidung außerhalb der Akte; angenommen wird sie ein Dokument der
 * Akte, abgelehnt wird die Datei gelöscht und nur die Zeile mit Grund bleibt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property string $title
 * @property HrDocumentCategory $hr_category
 * @property string|null $note
 * @property string $disk
 * @property string|null $path
 * @property string $original_name
 * @property string|null $mime
 * @property int $size
 * @property PersonnelFileSubmissionStatus $status
 * @property int|null $reviewer_user_id
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property int|null $document_id
 */
class PersonnelFileSubmission extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'user_id', 'title', 'hr_category', 'note', 'disk', 'path', 'original_name', 'mime', 'size',
        'status', 'reviewer_user_id', 'reviewed_at', 'review_note', 'document_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'hr_category' => HrDocumentCategory::class,
        'status' => PersonnelFileSubmissionStatus::class,
        'size' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo {
        return $this->belongsTo(Document::class);
    }
}
