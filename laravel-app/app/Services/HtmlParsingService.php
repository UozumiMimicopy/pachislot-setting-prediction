<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;

/**
 * HTML解析サービス
 *
 * 責務: HTMLからデータを抽出・パース
 * - DOM解析
 * - テーブルデータ抽出
 * - データフォーマット変換
 */
class HtmlParsingService
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
        $dom->loadHTML($html);
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
        $h1Node = $xpath->query("//h1")->item(0);
        if (!$h1Node) {
            Log::error("店名と日付の取得に失敗しました。");
            return false;
        }

        $h1Text = trim($h1Node->textContent);
        Log::info("取得した h1 テキスト:", [$h1Text]);

        // 日付と店舗名を抽出
        if (!preg_match('/(\d{1,2})\/(\d{1,2})[^ ]* (.+)/u', $h1Text, $matches)) {
            Log::error("日付フォーマットの解析に失敗しました: {$h1Text}");
            return false;
        }

        $month = (int)$matches[1]; // 月
        $day = (int)$matches[2];   // 日
        $storeName = trim($matches[3]); // 店舗名

        // MMDD 形式に変換
        $formattedDate = sprintf('%02d%02d', $month, $day);

        Log::info("日付変換結果:", ['original' => "{$month}/{$day}", 'formatted' => $formattedDate]);
        Log::info("抽出した店舗名:", [$storeName]);

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
        // テーブル取得
        $table = $xpath->query("//div[@class='table_wrap']/table")->item(0);
        if (!$table) {
            Log::error("データテーブルが見つかりません。");
            return false;
        }

        // ヘッダー取得
        $headers = $this->extractHeaders($table);
        if (!$headers) {
            return false;
        }

        // データ行の取得
        return $this->extractRows($table, $headers);
    }

    /**
     * テーブルヘッダーを抽出
     *
     * @param \DOMElement $table
     * @return array ヘッダー配列
     */
    private function extractHeaders($table): array
    {
        $headerRow = $table->getElementsByTagName('tr')->item(0);
        $headers = [];
        foreach ($headerRow->getElementsByTagName('th') as $th) {
            $headers[] = trim($th->textContent);
        }
        return $headers;
    }

    /**
     * テーブルデータ行を抽出
     *
     * @param \DOMElement $table
     * @param array $headers
     * @return array データ配列
     */
    private function extractRows($table, array $headers): array
    {
        $data = [];
        $rows = $table->getElementsByTagName('tr');

        for ($i = 1; $i < $rows->length; $i++) {
            $cols = $rows->item($i)->getElementsByTagName('td');
            if ($cols->length !== count($headers)) {
                continue;
            }

            $rowData = [];
            foreach ($headers as $index => $header) {
                $cell = $cols->item($index);
                $value = $this->extractCellValue($cell, $header);
                $rowData[$header] = $value;
            }

            $data[] = $rowData;
        }

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

        // 出率だけ特殊処理：<span class="rate" data-rate="114.3">114.3%</span> に対応
        if ($header === '出率') {
            $rateSpan = null;
            foreach ($cell->getElementsByTagName('span') as $span) {
                if ($span->hasAttribute('data-rate')) {
                    $rateSpan = $span;
                    break;
                }
            }
            if ($rateSpan) {
                $value = $rateSpan->getAttribute('data-rate');
            } else {
                $value = str_replace('%', '', $value); // 通常の % 除去
            }
        }

        // カンマ除去 & 数値変換（他の数値項目も共通処理）
        if (in_array($header, ['台番', 'G数', '差枚', '出率'])) {
            $value = str_replace(',', '', $value);
            $value = is_numeric($value) ? (float) $value : null;
        }

        return $value;
    }
}
