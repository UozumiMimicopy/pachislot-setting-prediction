<?php

namespace App\Services;

use App\Models\MasterStore;
use App\Models\MasterModel;
use DOMDocument;
use DOMXPath;
use Carbon\Carbon;

class MinrepoParser
{
    /**
     * HTMLファイルをパースしてデータを抽出
     *
     * @param string $filePath
     * @return array
     */
    public function parse(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        $html = file_get_contents($filePath);

        // DOMDocumentで解析
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        // 店舗名を抽出
        $storeName = $this->extractStoreName($xpath);

        // 日付を抽出
        $date = $this->extractDate($xpath);

        // データテーブルを抽出
        $machineData = $this->extractMachineData($xpath);

        return [
            'store_name' => $storeName,
            'date' => $date,
            'machines' => $machineData,
        ];
    }

    /**
     * 店舗名を抽出
     */
    private function extractStoreName(DOMXPath $xpath): string
    {
        // <span class="hall_name"><a href="...">プレイランドハッピー南6条店</a></span>
        $nodes = $xpath->query("//span[@class='hall_name']//a");
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }

        throw new \Exception("Store name not found in HTML");
    }

    /**
     * 日付を抽出
     */
    private function extractDate(DOMXPath $xpath): Carbon
    {
        // <time class="date" datetime="2025-01-02T15:53:33+09:00">2025年1月2日</time>
        $nodes = $xpath->query("//time[@class='date']");
        if ($nodes->length > 0) {
            $datetime = $nodes->item(0)->getAttribute('datetime');
            return Carbon::parse($datetime);
        }

        throw new \Exception("Date not found in HTML");
    }

    /**
     * 機種データを抽出
     */
    private function extractMachineData(DOMXPath $xpath): array
    {
        $machines = [];

        // データテーブルの行を取得
        // <table><tr><td>機種名</td><td>台番</td><td>差枚</td><td>G数</td><td>出率</td></tr>
        $rows = $xpath->query("//div[@class='table_wrap']//table//tr");

        foreach ($rows as $row) {
            $cells = $xpath->query('.//td', $row);

            // ヘッダー行をスキップ
            if ($cells->length !== 5) {
                continue;
            }

            $modelName = trim($cells->item(0)->textContent);
            $machineNumber = trim($cells->item(1)->textContent);
            $differentialMedals = trim($cells->item(2)->textContent);
            $gameCount = trim($cells->item(3)->textContent);
            $payoutRate = trim($cells->item(4)->textContent);

            // "-"の場合はnullに変換
            $differentialMedals = ($differentialMedals === '-') ? null : (int) str_replace(',', '', $differentialMedals);
            $gameCount = (int) str_replace(',', '', $gameCount);
            $payoutRate = ($payoutRate === '-') ? null : (float) str_replace('%', '', $payoutRate);

            $machines[] = [
                'model_name' => $modelName,
                'machine_number' => (int) $machineNumber,
                'differential_medals' => $differentialMedals,
                'game_count' => $gameCount,
                'payout_rate' => $payoutRate,
            ];
        }

        return $machines;
    }

    /**
     * 店舗名からmaster_storesのIDを取得
     */
    public function getStoreId(string $storeName): ?int
    {
        $store = MasterStore::where('name', 'like', "%{$storeName}%")->first();
        return $store ? $store->id : null;
    }

    /**
     * 機種名からmaster_modelsのIDを取得
     */
    public function getModelId(string $modelName): ?int
    {
        $model = MasterModel::where('name', $modelName)->first();
        return $model ? $model->id : null;
    }
}
