<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterStore extends Model
{
    protected $table = 'master_stores';

    protected $fillable = [
        'series_id',
        'name',
        'short_name',
        'details',
        'specific_date',
        'anniversary_date',
        'is_collected',
    ];
}
