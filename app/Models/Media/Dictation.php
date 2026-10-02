<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Dictation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Media;

use App\Enums\Media\DictationStatus;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;

/**
 * Sprachdiktat (MVP-1060): Audio bis zur Transkription, danach Transkript und
 * Gliederung (verschlüsselt). Das Ergebnis ist ein Vorschlag fürs Formular.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $created_by
 * @property string $context
 * @property DictationStatus $status
 * @property string $locale
 * @property string|null $audio_disk
 * @property string|null $audio_path
 * @property string|null $transcript
 * @property array<string, string>|null $structured
 * @property string|null $failure
 */
class Dictation extends Model {
    use BelongsToOrganization;
    use HasSqid;

    /** Kontexte mit eigener Gliederung; Schlüssel = Feldnamen der Gliederung. */
    public const CONTEXTS = [
        'diary' => ['activity', 'work_done', 'defects'],
        'protocol' => ['note', 'defects'],
    ];

    protected $fillable = ['organization_id', 'created_by', 'context', 'status', 'locale', 'audio_disk', 'audio_path', 'transcript', 'structured', 'failure'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'pending'];

    /** @var array<string, string> */
    protected $casts = [
        'status' => DictationStatus::class,
        'transcript' => 'encrypted',
        'structured' => 'encrypted:array',
    ];
}
