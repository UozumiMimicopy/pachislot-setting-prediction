<?php

namespace App\Services;

use App\Models\MasterStore;
use App\Models\MasterModel;
use DOMDocument;
use DOMXPath;
use Carbon\Carbon;

class AnasuroParser
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
        $storeName = $this->extractStoreName($xpath, $filePath);

        // 日付を抽出
        $date = $this->extractDate($xpath, $filePath);

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
     * ファイル名から抽出: "2025_10_24 プレイランドハッピー南6条店 データまとめ - アナスロ.html"
     */
    private function extractStoreName(DOMXPath $xpath, string $filePath): string
    {
        // ファイル名から店舗名を抽出
        $fileName = basename($filePath);

        // パターン: "YYYY_MM_DD 店舗名 データまとめ - アナスロ.html"
        if (preg_match('/^\d{4}_\d{2}_\d{2}\s+(.+?)\s+データまとめ\s*-\s*アナスロ\.html$/u', $fileName, $matches)) {
            return trim($matches[1]);
        }

        throw new \Exception("Store name not found in filename: {$fileName}");
    }

    /**
     * 日付を抽出
     * ファイル名から抽出: "2025_10_24 プレイランドハッピー南6条店 データまとめ - アナスロ.html"
     */
    private function extractDate(DOMXPath $xpath, string $filePath): Carbon
    {
        // ファイル名から日付を抽出
        $fileName = basename($filePath);

        // パターン: "YYYY_MM_DD 店舗名 データまとめ - アナスロ.html"
        if (preg_match('/^(\d{4})_(\d{2})_(\d{2})/', $fileName, $matches)) {
            return Carbon::create($matches[1], $matches[2], $matches[3]);
        }

        throw new \Exception("Date not found in filename: {$fileName}");
    }

    /**
     * 機種データを抽出
     * アナスロHTMLの11カラム構造:
     * 機種名, 台番号, G数, 差枚, BB, RB, ART, 合成確率, BB確率, RB確率, ART確率
     */
    private function extractMachineData(DOMXPath $xpath): array
    {
        $machines = [];

        // データテーブルの行を取得
        // <table id="all_data_table" class="fixed_get_medals_table"><tbody><tr><td>...</td></tr></tbody></table>
        $rows = $xpath->query("//table[@id='all_data_table']//tbody//tr");

        foreach ($rows as $row) {
            $cells = $xpath->query('.//td', $row);

            // ヘッダー行やデータが不完全な行をスキップ (11カラム必須)
            if ($cells->length !== 11) {
                continue;
            }

            // テーブルヘッダーっぽい行をスキップ（機種名に「機種名」という文字列が含まれる場合）
            $firstCellText = trim($cells->item(0)->textContent);
            if ($firstCellText === '機種名') {
                continue;
            }

            // データを抽出
            $modelName = trim($cells->item(0)->textContent);
            $machineNumber = trim($cells->item(1)->textContent);
            $gameCount = trim($cells->item(2)->textContent);
            $differentialMedals = trim($cells->item(3)->textContent);
            $bbCount = trim($cells->item(4)->textContent);
            $rbCount = trim($cells->item(5)->textContent);
            $artCount = trim($cells->item(6)->textContent);
            // 7: 合成確率, 8: BB確率, 9: RB確率, 10: ART確率 は現時点では使用しない

            // 数値変換 ("-"の場合はnullに変換)
            $gameCount = (int) str_replace(',', '', $gameCount);
            $differentialMedals = ($differentialMedals === '-') ? null : (int) str_replace(['+', ','], '', $differentialMedals);
            $bbCount = ($bbCount === '-') ? null : (int) str_replace(',', '', $bbCount);
            $rbCount = ($rbCount === '-') ? null : (int) str_replace(',', '', $rbCount);
            $artCount = ($artCount === '-') ? null : (int) str_replace(',', '', $artCount);

            // 台番号が数値でない場合はスキップ
            if (!is_numeric($machineNumber)) {
                continue;
            }

            $machines[] = [
                'model_name' => $modelName,
                'machine_number' => (int) $machineNumber,
                'game_count' => $gameCount,
                'differential_medals' => $differentialMedals,
                'bb_count' => $bbCount,
                'rb_count' => $rbCount,
                'art_count' => $artCount,
                'payout_rate' => null, // アナスロには出率データがないのでnull
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
