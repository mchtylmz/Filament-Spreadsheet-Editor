<?php

namespace Mivento\FilamentSpreadsheetEditor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SpreadsheetCellAudit extends Model
{
    public const UPDATED_AT = null;

    /** @var array<int, string> */
    protected $fillable = [
        'user_id',
        'model_type',
        'model_id',
        'field',
        'old_value',
        'new_value',
        'batch_uuid',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'json',
            'new_value' => 'json',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
