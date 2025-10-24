<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterModel extends Model
{
    protected $table = 'master_models';

    protected $fillable = [
        'name',
        'category_id',
        'details',
    ];
}
