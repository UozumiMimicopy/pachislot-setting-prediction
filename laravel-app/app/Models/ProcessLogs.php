<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessLogs extends Model
{
    use HasFactory;

    protected $table = 'process_logs';

    protected $fillable = [
        'store_id',
        'process_date',
        'status',
    ];

    public static function isProcessed($storeId, $date)
    {
        return self::where('store_id', $storeId)
            ->where('process_date', $date)
            ->whereIn('status', ['processed', 'error'])
            ->exists();
    }
    
    public static function isSuccessfullyProcessed($storeId, $date)
    {
        return self::where('store_id', $storeId)
            ->where('process_date', $date)
            ->where('status', 'processed')
            ->exists();
    }
    
    public static function markProcessing($storeId, $date)
    {
        return self::updateOrCreate(
            ['store_id' => $storeId, 'process_date' => $date],
            ['status' => 'processing']
        );
    }

    public static function markCompleted($storeId, $date)
    {
        return self::updateOrCreate(
            ['store_id' => $storeId, 'process_date' => $date],
            ['status' => 'processed']
        );
    }

    public static function markError($storeId, $date)
    {
        return self::updateOrCreate(
            ['store_id' => $storeId, 'process_date' => $date],
            ['status' => 'error']
        );
    }
}
