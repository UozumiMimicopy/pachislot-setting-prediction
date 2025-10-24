<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;

/**
 * アナスロHTML解析サービス
 *
 * 責務: アナスロのHTMLからデータを抽出・パース
 * - DOM解析
 * - テーブルデータ抽出 (11カラム)
 * - BB/RB/ART回数データ取得
 */
class AnasuroParsingService
{
    /**
     * HTMLをパースしてデータ配列に変換
     *
     * @param string $html HTML文字列
     * @return array|false パース結果 ['store_name' => string, 'date' => string, 'data' => array] または false
     */
    public function parseHtml(string $html)
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true); // HTML解析エラーを抑制
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // 店名と日付の取得
        $metadata = $this->extractMetadata($xpath);
        if (!$metadata) {
            return false;
        }

        // テーブルデータの取得
        $tableData = $this->extractTableData($xpath);
        if (!$tableData) {
            return false;
        }

        return [
            'store_name' => $metadata['store_name'],
            'date' => $metadata['date'],
            'data' => $tableData
        ];
    }

    /**
     * XPathからメタデータ（店名・日付）を抽出
     *
     * @param DOMXPath $xpath
     * @return array|false ['store_name' => string, 'date' => string] または false
     */
    private function extractMetadata(DOMXPath $xpath)
    {
        // titleタグから店舗名と日付を取得
        // 例: "2025/10/24 プレイランドハッピー南6条店 データまとめ - アナスロ"
        $titleNode = $xpath->query("//title")->item(0);
        if (!$titleNode) {
            Log::error("[AnasuroParser] titleタグが見つかりません");
            return false;
        }

        $title = trim($titleNode->textContent);
        Log::info("[AnasuroParser] 取得したtitleテキスト: {$title}");

        // 日付と店舗名を抽出
        if (!preg_match('/(\d{4})\/(\d{1,2})\/(\d{1,2})\s+(.+?)\s+データまとめ\s*-\s*アナスロ/u', $title, $matches)) {
            Log::error("[AnasuroParser] titleフォーマットの解析に失敗: {$title}");
            return false;
        }

        $year = (int)$matches[1];
        $month = (int)$matches[2];
        $day = (int)$matches[3];
        $storeName = trim($matches[4]);

        // MMDD 形式に変換
        $formattedDate = sprintf('%02d%02d', $month, $day);

        Log::info("[AnasuroParser] 日付変換結果: {$year}/{$month}/{$day} -> {$formattedDate}");
        Log::info("[AnasuroParser] 抽出した店舗名: {$storeName}");

        return [
            'store_name' => $storeName,
            'date' => $formattedDate
        ];
    }

    /**
     * XPathからテーブルデータを抽出
     *
     * @param DOMXPath $xpath
     * @return array|false テーブルデータ配列 または false
     */
    private function extractTableData(DOMXPath $xpath)
    {
        // アナスロのテーブル取得
        // <table id="all_data_table" class="fixed_get_medals_table">
        $rows = $xpath->query("//table[@id='all_data_table']//tbody//tr");
        if ($rows->length === 0) {
            Log::error("[AnasuroParser] データテーブルが見つかりません");
            return false;
        }

        Log::info("[AnasuroParser] テーブル行数: " . $rows->length);

        $data = [];
        $headers = ['機種名', '台番', 'G数', '差枚', 'BB', 'RB', 'ART', '合成確率', 'BB確率', 'RB確率', 'ART確率'];

        foreach ($rows as $row) {
            $cells = $xpath->query('.//td', $row);

            // 11カラム必須
            if ($cells->length !== 11) {
                continue;
            }

            // ヘッダー行スキップ
            $firstCellText = trim($cells->item(0)->textContent);
            if ($firstCellText === '機種名') {
                continue;
            }

            $rowData = [];
            foreach ($headers as $index => $header) {
                $cell = $cells->item($index);
                $value = $this->extractCellValue($cell, $header);
                $rowData[$header] = $value;
            }

            // 台番が数値でない場合はスキップ
            if (!is_numeric($rowData['台番'])) {
                continue;
            }

            $data[] = $rowData;
        }

        Log::info("[AnasuroParser] 抽出したデータ行数: " . count($data));

        return $data;
    }

    /**
     * セルの値を抽出・フォーマット
     *
     * @param \DOMElement $cell
     * @param string $header
     * @return mixed セルの値
     */
    private function extractCellValue($cell, string $header)
    {
        $value = trim($cell->textContent);

        // カンマ除去 & 数値変換
        if (in_array($header, ['台番', 'G数', 'BB', 'RB', 'ART'])) {
            $value = str_replace(',', '', $value);
            $value = ($value === '-') ? null : (is_numeric($value) ? (int) $value : null);
        }

        // 差枚は + を除去
        if ($header === '差枚') {
            $value = str_replace(['+', ','], '', $value);
            $value = ($value === '-') ? null : (is_numeric($value) ? (int) $value : null);
        }

        // 確率系は文字列のまま（DB保存しないため）
        if (in_array($header, ['合成確率', 'BB確率', 'RB確率', 'ART確率'])) {
            return $value;
        }

        return $value;
    }
}
