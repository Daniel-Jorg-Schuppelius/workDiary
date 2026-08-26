<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DsarPortal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Portalkonfiguration des Betroffenen-Selbstmeldeportals (G11, MVP-728) — eine
 * Instanz je Organisation. `public_slug` ist bewusst nicht aus dem Org-Namen
 * ableitbar und unabhaengig rotierbar (gleiche Abwaegung wie beim
 * Hinweisgeber-Portal, {@see \App\Models\Whistleblowing\Portal}).
 *
 * @property int $organization_id
 * @property string $public_slug
 * @property bool $is_enabled
 * @property bool $allow_attachments
 * @property string|null $intro_text
 * @property string|null $default_locale
 */
class DsarPortal extends Model {
    use BelongsToOrganization;

    protected $table = 'privacy_dsar_portals';

    protected $fillable = [
        'organization_id',
        'public_slug',
        'is_enabled',
        'allow_attachments',
        'intro_text',
        'default_locale',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_enabled' => 'boolean',
        'allow_attachments' => 'boolean',
    ];
}
