<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentRenderProfileVersion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\DocumentDesign;

use App\Enums\DocumentDesign\{InformationBlock, InformationBlockState};
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unveränderlicher Layoutstand eines Renderprofils (MVP-300). Nur Entwürfe
 * sind editierbar; mit der Aktivierung wird die Version eingefroren
 * (saving-Guard) und die vorherige aktive Version als `superseded` markiert.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $document_render_profile_id
 * @property int $version
 * @property string $status
 * @property int|null $first_asset_id
 * @property int|null $following_asset_id
 * @property array<string, mixed> $layout
 * @property array<string, array<string, mixed>> $block_rules
 * @property array<string, mixed> $table_style
 * @property string|null $checksum
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $activated_at
 * @property int|null $activated_by
 */
class DocumentRenderProfileVersion extends Model {
    use BelongsToOrganization;

    use HasSqid;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'organization_id',
        'document_render_profile_id',
        'version',
        'status',
        'first_asset_id',
        'following_asset_id',
        'layout',
        'block_rules',
        'table_style',
        'override_sections',
        'checksum',
        'created_by',
        'activated_at',
        'activated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'version' => 'integer',
        'layout' => 'array',
        'block_rules' => 'array',
        'table_style' => 'array',
        'override_sections' => 'array',
        'activated_at' => 'datetime',
    ];

    protected static function booted(): void {
        // Unveränderlichkeits-Garde: einmal aktivierte (oder abgelöste)
        // Versionen dürfen ihren Layoutstand nicht mehr ändern — nur der
        // Statuswechsel active→superseded bleibt erlaubt.
        static::updating(function (self $version): void {
            if ($version->getOriginal('status') === self::STATUS_DRAFT) {
                return;
            }
            $changed = array_keys($version->getDirty());
            $allowed = ['status', 'updated_at'];
            if (array_diff($changed, $allowed) !== []) {
                throw new \RuntimeException('Aktivierte Profilversionen sind unveränderlich.');
            }
        });
    }

    /** @return BelongsTo<DocumentRenderProfile, $this> */
    public function profile(): BelongsTo {
        return $this->belongsTo(DocumentRenderProfile::class, 'document_render_profile_id');
    }

    /** @return BelongsTo<LetterheadAsset, $this> */
    public function firstAsset(): BelongsTo {
        return $this->belongsTo(LetterheadAsset::class, 'first_asset_id');
    }

    /** @return BelongsTo<LetterheadAsset, $this> */
    public function followingAsset(): BelongsTo {
        return $this->belongsTo(LetterheadAsset::class, 'following_asset_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool {
        return $this->status === self::STATUS_DRAFT;
    }

    public function blockState(InformationBlock $block): InformationBlockState {
        $raw = $this->block_rules[$block->value]['state'] ?? null;

        return InformationBlockState::tryFrom((string) $raw) ?? $block->defaultState();
    }
}
