<?php

namespace App\Services;

use App\Models\MasterStore;
use App\Models\MasterModel;
use Illuminate\Support\Facades\Log;

/**
 * データ変換サービス
 *
 * 責務: パースされたデータをDBモデルに変換
 * - 店舗名 → 店舗ID解決
 * - 機種名 → 機種ID解決
 * - 新機種の検出
 * - ログスロットデータの構築
 */
class DataTransformationService
{
    /**
     * パースされたデータをDBオブジェクトに変換
     *
     * @param array $parsedData HtmlParsingServiceから返されたデータ
     * @param string $date YYYY-MM-DD形式の日付
     * @return array|false ['new_models' => array, 'logs_slot' => array] または false
     */
    public function transformToDbObjects(array $parsedData, string $date)
    {
        $newModels = [];
        $logsSlots = [];

        // 店舗IDを取得
        $storeId = $this->resolveStoreId($parsedData['store_name']);
        if (!$storeId) {
            return false;
        }

        foreach ($parsedData['data'] as $row) {
            // みんレポ形式とアナスロ形式の両方に対応
            $modelName = $row['機種'] ?? $row['機種名'] ?? '';
            $slotNumber = $row['台番'] ?? $row['台番号'] ?? 0;
            $playCount = $row['G数'] ?? 0;
            $difference = $row['差枚'] ?? 0;
            $payoutRate = $row['出率'] ?? null;

            // 既存の機種を検索
            $model = MasterModel::where('name', $modelName)->first();
            if (!$model) {
                // 既存の機種がない場合、新台リストに追加（作成はしない）
                if (!in_array($modelName, array_column($newModels, 'name'))) {
                    $newModels[] = ['name' => $modelName];
                }
            } else {
                // 既存機種がある場合、台データを `logsSlots` に追加
                $logsSlots[] = $this->buildLogSlotData(
                    $model->id,
                    $slotNumber,
                    $playCount,
                    $difference,
                    $storeId,
                    $date,
                    $payoutRate,
                    $row
                );
            }
        }

        // デバッグ用：中身をファイルに保存
        $this->saveDebugOutput($newModels, $logsSlots);

        return [
            'new_models' => $newModels,
            'logs_slot' => $logsSlots
        ];
    }

    /**
     * 店舗名から店舗IDを解決
     *
     * @param string $storeName
     * @return int|false 店舗ID または false
     */
    private function resolveStoreId(string $storeName)
    {
        $store = MasterStore::where('name', $storeName)->first();
        if (!$store) {
            Log::error("店舗が見つかりません: {$storeName}");
            return false;
        }
        return $store->id;
    }

    /**
     * ログスロットデータを構築
     *
     * @param int $modelId
     * @param int $slotNumber
     * @param int $playCount
     * @param int $difference
     * @param int $storeId
     * @param string $date
     * @param float|null $payoutRate
     * @param array $rawData
     * @return array
     */
    private function buildLogSlotData(
        int $modelId,
        int $slotNumber,
        int $playCount,
        int $difference,
        int $storeId,
        string $date,
        ?float $payoutRate,
        array $rawData
    ): array {
        return [
            'model_id' => $modelId,
            'slot_number' => $slotNumber,
            'game_count' => $playCount,
            'score_difference' => $difference,
            'store_id' => $storeId,
            'log_date' => $date,
            'payout_rate' => $payoutRate,
            'bb_count' => $rawData['BB'] ?? null,
            'rb_count' => $rawData['RB'] ?? null,
            'art_count' => $rawData['ART'] ?? null,
            'data' => json_encode($rawData),
        ];
    }

    /**
     * デバッグ用に処理結果をファイル保存
     *
     * @param array $newModels
     * @param array $logsSlots
     * @return void
     */
    private function saveDebugOutput(array $newModels, array $logsSlots): void
    {
        $result = [
            'new_model' => $newModels,
            'logs_slot' => $logsSlots
        ];
        $outputPath = storage_path('logs/processed_data.json');

        // ディレクトリが存在しない場合は作成
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($outputPath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
