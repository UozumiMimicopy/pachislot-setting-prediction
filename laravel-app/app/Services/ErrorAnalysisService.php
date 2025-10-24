<?php

namespace App\Services;

use App\Models\MasterModel;
use App\Models\MinrepoData;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * エラー分析サービス
 *
 * 責務: ログデータの分析とエラー検出
 * - 統計計算
 * - 異常検知
 * - レポート生成
 */
class ErrorAnalysisService
{
    /**
     * エラー理由の列挙
     */
    const ERROR_UNKNOWN = 'unknown';
    const ERROR_ABNORMAL_SLOT_COUNT = 'abnormal_slot_count';
    const ERROR_ABNORMAL_SLOT_DATA = 'abnormal_slot_data';

    /**
     * エラー分析を実行してレポートを生成
     *
     * @param int $storeId 店舗ID
     * @param string $logDate ログ日付
     * @return array エラー理由の配列
     */
    public function analyzeAndGenerateReport(int $storeId, string $logDate): array
    {
        $records = $this->fetchRecords($storeId, $logDate);

        if ($records->isEmpty()) {
            return [self::ERROR_UNKNOWN];
        }

        // 基本統計の計算
        $basicStats = $this->calculateBasicStats($records);

        // 常設台の分析
        $permanentSlotAnalysis = $this->analyzePermanentSlots($storeId, $logDate, $records);

        // 機種別統計
        $modelStats = $this->calculateModelStats($records);

        // エラー検出
        $errors = $this->detectErrors($records, $permanentSlotAnalysis);

        // レポート生成
        $this->generateReport($storeId, $logDate, $basicStats, $permanentSlotAnalysis, $modelStats, $errors);

        return $errors;
    }

    /**
     * レコードを取得
     *
     * @param int $storeId
     * @param string $logDate
     * @return \Illuminate\Support\Collection
     */
    private function fetchRecords(int $storeId, string $logDate)
    {
        return MinrepoData::where('store_id', $storeId)
            ->where('date', $logDate)
            ->get();
    }

    /**
     * 基本統計を計算
     *
     * @param \Illuminate\Support\Collection $records
     * @return array
     */
    private function calculateBasicStats($records): array
    {
        $totalGames = $records->sum('game_count');
        $totalScores = $records->sum('score_difference');
        $slotCount = $records->count();

        $storePayoutRate = $totalGames > 0
            ? (($totalScores + ($totalGames * 3)) / ($totalGames * 3)) * 100
            : null;

        return [
            'total_games' => $totalGames,
            'total_scores' => $totalScores,
            'slot_count' => $slotCount,
            'store_payout_rate' => $storePayoutRate
        ];
    }

    /**
     * 常設台の分析
     *
     * @param int $storeId
     * @param string $logDate
     * @param \Illuminate\Support\Collection $records
     * @return array
     */
    private function analyzePermanentSlots(int $storeId, string $logDate, $records): array
    {
        $fromDate = Carbon::parse($logDate)->subDays(10)->toDateString();
        $toDate = $logDate;

        // 過去10日のうち8回以上出現したmachine_numberを抽出（常設台）
        $baselineSlotNumbers = MinrepoData::where('store_id', $storeId)
            ->whereBetween('date', [$fromDate, $toDate])
            ->select('machine_number', DB::raw('COUNT(*) as appear_count'))
            ->groupBy('machine_number')
            ->havingRaw('COUNT(*) >= 8')
            ->pluck('machine_number')
            ->toArray();

        $expectedCount = count($baselineSlotNumbers);

        // 当日分のスロット番号を取得して比較
        $actualSlotNumbers = $records->pluck('machine_number')->map(fn($n) => (int)$n)->toArray();
        $baselineSlotNumbers = array_map('intval', $baselineSlotNumbers);

        $missingSlotNumbers = array_diff($baselineSlotNumbers, $actualSlotNumbers);
        $missingCount = count($missingSlotNumbers);

        // 警告判定
        $isAbnormal = false;
        if ($expectedCount > 0) {
            $missingRatio = $missingCount / $expectedCount;
            $isAbnormal = $missingRatio > 0.05;
        } else {
            $isAbnormal = true;
        }

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'expected_count' => $expectedCount,
            'actual_count' => count($actualSlotNumbers),
            'missing_count' => $missingCount,
            'missing_machine_numbers' => $missingSlotNumbers,
            'is_abnormal' => $isAbnormal
        ];
    }

    /**
     * 機種別統計を計算
     *
     * @param \Illuminate\Support\Collection $records
     * @return \Illuminate\Support\Collection
     */
    private function calculateModelStats($records)
    {
        $modelStats = $records->groupBy('model_id')->map(function ($items) {
            $gameSum = $items->sum('game_count');
            $scoreSum = $items->sum('score_difference');
            return [
                'avg_score' => $items->avg('score_difference'),
                'payout_rate' => $gameSum > 0
                    ? (($scoreSum + ($gameSum * 3)) / ($gameSum * 3)) * 100
                    : null,
            ];
        });

        return $modelStats->sortByDesc('avg_score');
    }

    /**
     * エラーを検出
     *
     * @param \Illuminate\Support\Collection $records
     * @param array $permanentSlotAnalysis
     * @return array
     */
    private function detectErrors($records, array $permanentSlotAnalysis): array
    {
        $errors = [];

        // 台数異常チェック
        if ($permanentSlotAnalysis['is_abnormal']) {
            $errors[] = self::ERROR_ABNORMAL_SLOT_COUNT;
        }

        // マイナス差枚が一つもない → 異常
        $hasNoNegative = $records->every(fn($record) => $record->score_difference >= 0);
        if ($hasNoNegative) {
            $errors[] = self::ERROR_ABNORMAL_SLOT_DATA;
        }

        return $errors;
    }

    /**
     * レポートを生成
     *
     * @param int $storeId
     * @param string $logDate
     * @param array $basicStats
     * @param array $permanentSlotAnalysis
     * @param \Illuminate\Support\Collection $modelStats
     * @param array $errors
     * @return void
     */
    private function generateReport(
        int $storeId,
        string $logDate,
        array $basicStats,
        array $permanentSlotAnalysis,
        $modelStats,
        array $errors
    ): void {
        $content = $this->buildReportContent(
            $storeId,
            $logDate,
            $basicStats,
            $permanentSlotAnalysis,
            $modelStats
        );

        $filenameSuffix = $this->buildFilenameSuffix($permanentSlotAnalysis, $errors);

        $this->writeReport($storeId, $logDate, $content, $filenameSuffix);
    }

    /**
     * レポート内容を構築
     *
     * @param int $storeId
     * @param string $logDate
     * @param array $basicStats
     * @param array $permanentSlotAnalysis
     * @param \Illuminate\Support\Collection $modelStats
     * @return string
     */
    private function buildReportContent(
        int $storeId,
        string $logDate,
        array $basicStats,
        array $permanentSlotAnalysis,
        $modelStats
    ): string {
        $content = "Store ID: {$storeId}\n";
        $content .= "Log Date: {$logDate}\n";
        $content .= "Slot Count: {$basicStats['slot_count']}\n";
        $content .= "Store Payout Rate: " .
            ($basicStats['store_payout_rate'] !== null
                ? number_format($basicStats['store_payout_rate'], 2) . "%"
                : 'N/A') . "\n";

        // 台数警告
        $content .= $this->buildCountWarning($permanentSlotAnalysis) . "\n";

        // 欠損台番号
        $content .= $this->buildMissingSlotMessage($permanentSlotAnalysis) . "\n";

        // デバッグ情報
        $content .= $this->buildDebugMessage($permanentSlotAnalysis) . "\n";

        // 機種別統計
        $content .= $this->buildModelStatsTable($modelStats);

        return $content;
    }

    /**
     * 台数警告メッセージを構築
     *
     * @param array $analysis
     * @return string
     */
    private function buildCountWarning(array $analysis): string
    {
        if ($analysis['expected_count'] > 0) {
            if ($analysis['is_abnormal']) {
                return "WARNING: 異常台数（基準台数: {$analysis['expected_count']}台, 実測: {$analysis['actual_count']}台, 欠損台数: {$analysis['missing_count']}台）";
            } else {
                return "OK: 台数正常（基準台数: {$analysis['expected_count']}台, 実測: {$analysis['actual_count']}台, 欠損台数: {$analysis['missing_count']}台）";
            }
        } else {
            return "WARNING: 常設台番号が取得できませんでした（データ不足の可能性）";
        }
    }

    /**
     * 欠損台番号メッセージを構築
     *
     * @param array $analysis
     * @return string
     */
    private function buildMissingSlotMessage(array $analysis): string
    {
        $missingCount = $analysis['missing_count'];
        if ($missingCount === 0) {
            return '';
        }

        $displayList = array_slice($analysis['missing_machine_numbers'], 0, 50);
        $message = "欠損台番号(50台まで): " . implode(', ', $displayList) . "\n";

        if ($missingCount > 50) {
            $message .= "...以降 " . ($missingCount - 50) . " 台が省略\n";
        }

        return $message;
    }

    /**
     * デバッグメッセージを構築
     *
     * @param array $analysis
     * @return string
     */
    private function buildDebugMessage(array $analysis): string
    {
        $message = "DEBUG: 常設台番号抽出範囲: {$analysis['from_date']} ～ {$analysis['to_date']}\n";
        $message .= "DEBUG: 抽出された常設台番号数: {$analysis['expected_count']}台\n";
        return $message;
    }

    /**
     * 機種別統計テーブルを構築
     *
     * @param \Illuminate\Support\Collection $modelStats
     * @return string
     */
    private function buildModelStatsTable($modelStats): string
    {
        $modelNames = MasterModel::whereIn('id', $modelStats->keys())
            ->pluck('name', 'id');

        $content = "Model Stats (sorted by Average Score):\n";
        $content .= "+------------------------+------------+--------------+\n";
        $content .= "| Model Name             | Avg Score  | Payout Rate  |\n";
        $content .= "+------------------------+------------+--------------+\n";

        foreach ($modelStats as $modelId => $stats) {
            $modelName = $modelNames[$modelId] ?? 'Unknown';
            $shortName = mb_strimwidth($modelName, 0, 22, '…');
            $avgScore = $stats['avg_score'] !== null ? number_format($stats['avg_score'], 2) : 'N/A';
            $payoutRate = $stats['payout_rate'] !== null ? number_format($stats['payout_rate'], 2) . '%' : 'N/A';

            $content .= sprintf("| %-22s | %10s | %12s |\n", $shortName, $avgScore, $payoutRate);
        }

        $content .= "+------------------------+------------+--------------+\n";

        return $content;
    }

    /**
     * ファイル名サフィックスを構築
     *
     * @param array $permanentSlotAnalysis
     * @param array $errors
     * @return string
     */
    private function buildFilenameSuffix(array $permanentSlotAnalysis, array $errors): string
    {
        $suffix = '';

        if ($permanentSlotAnalysis['missing_count'] > 0) {
            $suffix .= '_欠損あり';
        }

        if (in_array(self::ERROR_ABNORMAL_SLOT_DATA, $errors)) {
            $suffix .= '_マイナス差枚数異常';
        }

        return $suffix;
    }

    /**
     * レポートをファイルに書き込み
     *
     * @param int $storeId
     * @param string $logDate
     * @param string $content
     * @param string $filenameSuffix
     * @return void
     */
    private function writeReport(int $storeId, string $logDate, string $content, string $filenameSuffix = ''): void
    {
        $dir = public_path('storage/error_handle');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = "{$dir}/store_{$storeId}_date_{$logDate}{$filenameSuffix}.txt";
        file_put_contents($filename, $content);
    }
}
