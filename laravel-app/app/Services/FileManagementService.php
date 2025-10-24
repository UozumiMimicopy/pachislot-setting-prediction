<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * ファイル管理サービス
 *
 * 責務: ファイルパスの解決とバリデーション
 * - ストアデータファイルパスの解決
 * - ファイル名のバリデーション
 * - ファイル存在確認
 */
class FileManagementService
{
    /**
     * ストアデータファイルのパスを取得
     *
     * @param string $storeName 店舗名
     * @param string|null $date 日付（YYYYMMDD形式、nullの場合は今日）
     * @return string|false ファイルパス または false（ファイルが存在しない場合）
     */
    public function getDataPath(string $storeName, ?string $date = null)
    {
        $date = $date ?? now()->format('Ymd');
        $dataPath = storage_path("app/public/store_data/{$date}_{$storeName}.html");

        if (!file_exists($dataPath)) {
            Log::debug("ファイルが見つかりません: {$dataPath}");
            return false;
        }

        return $dataPath;
    }

    /**
     * ファイル名のバリデーション
     *
     * @param string $fileName
     * @return bool 有効な形式の場合true
     */
    public function validateFileName(string $fileName): bool
    {
        return preg_match('/^\d{8}_.+\.html$/', $fileName) === 1;
    }
}
