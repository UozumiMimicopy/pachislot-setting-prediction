<?php

namespace App\Services;

use App\Models\MasterModel;
use App\Models\MinrepoData;
use Illuminate\Support\Facades\Log;

/**
 * データ保存サービス
 *
 * 責務: 変換されたデータをDBに保存
 * - 新機種の登録
 * - ログスロットデータの保存（upsert）
 * - トランザクション管理
 */
class DataStorageService
{
    /**
     * 変換されたデータをDBに保存
     *
     * @param array $processedData ['new_models' => array, 'logs_slot' => array]
     * @return bool 成功時true
     */
    public function storeData(array $processedData): bool
    {
        Log::info("storeData開始");

        // 新機種の登録
        $this->storeNewModels($processedData['new_models']);

        // ログスロットデータの保存
        $this->storeLogSlots($processedData['logs_slot']);

        return true;
    }

    /**
     * 新機種をDBに登録
     *
     * @param array $newModels
     * @return void
     */
    private function storeNewModels(array $newModels): void
    {
        foreach ($newModels as $modelData) {
            $newModel = MasterModel::create([
                'id' => $modelData['model_id'] ?? null,
                'name' => $modelData['name'],
                'category_id' => 1,
                'detail' => ""
            ]);
            Log::info("新台登録: {$newModel->name}");
        }
    }

    /**
     * ログスロットデータをDBに保存（upsert）
     *
     * @param array $logsSlots
     * @return void
     */
    private function storeLogSlots(array $logsSlots): void
    {
        foreach ($logsSlots as $logsSlotData) {
            MinrepoData::updateOrCreate(
                [
                    'store_id' => $logsSlotData['store_id'],
                    'model_id' => $logsSlotData['model_id'],
                    'machine_number' => $logsSlotData['slot_number'],
                    'date' => $logsSlotData['log_date'],
                ],
                [
                    'game_count' => $logsSlotData['game_count'],
                    'differential_medals' => $logsSlotData['score_difference'],
                    'payout_rate' => $logsSlotData['payout_rate'] ?? null,
                    'bb_count' => $logsSlotData['bb_count'] ?? null,
                    'rb_count' => $logsSlotData['rb_count'] ?? null,
                    'art_count' => $logsSlotData['art_count'] ?? null,
                ]
            );
        }
    }
}
