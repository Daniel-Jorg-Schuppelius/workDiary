<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyDiaryEntry extends Model {
    protected $connection = 'legacy';

    protected $table = 'tagebuch';

    public $timestamps = false;

    protected $guarded = [];

    protected $primaryKey = 'id';

    protected function casts(): array {
        return [
            'aktuell' => 'datetime',
            'von' => 'datetime',
            'bis' => 'datetime',
            'gelesen' => 'integer',
        ];
    }

    public function author(): BelongsTo {
        return $this->belongsTo(LegacyUser::class, 'user', 'id');
    }

    public function user(): BelongsTo {
        return $this->author();
    }

    #[Scope]
    protected function active(Builder $query): void {
        $query->where('bis', '>=', now()->subDays(30));
    }

    public function statusLabel(): string {
        return match ($this->gelesen) {
            -1 => __('Erledigt'),
            1 => __('Bestätigt'),
            2 => __('Offen'),
            3 => __('Problem'),
            default => __('Unbekannt'),
        };
    }

    public function statusTone(): string {
        return match ($this->gelesen) {
            -1 => 'done',
            1 => 'progress',
            2 => 'open',
            3 => 'alert',
            default => 'neutral',
        };
    }
}
