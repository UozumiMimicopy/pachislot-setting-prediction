<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinrepoData extends Model
{
    protected $table = 'minrepo_data';

    protected $fillable = [
        'store_id',
        'model_id',
        'date',
        'machine_number',
        'differential_medals',
        'game_count',
        'payout_rate',
        'bb_count',
        'rb_count',
        'art_count',
    ];

    protected $casts = [
        'date' => 'date',
        'differential_medals' => 'integer',
        'game_count' => 'integer',
        'payout_rate' => 'decimal:2',
        'bb_count' => 'integer',
        'rb_count' => 'integer',
        'art_count' => 'integer',
    ];

    /**
     * 店舗とのリレーション
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(MasterStore::class, 'store_id');
    }

    /**
     * 機種とのリレーション
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(MasterModel::class, 'model_id');
    }
}
