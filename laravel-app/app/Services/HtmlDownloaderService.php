<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HtmlDownloaderService
{
    /**
     * 指定のURL一覧からHTMLをダウンロードし保存する
     * 
     * @param array $urls
     * @return array 保存成功したファイルパスの配列
     */
    public function downloadFromUrls(array $urls): array
    {
        $savedFiles = [];

        foreach ($urls as $url) {
            $path = $this->downloadAndSave($url);
            if ($path) {
                $savedFiles[] = $path;
            }
        }

        return $savedFiles;
    }

    /**
     * テキストファイルからURLを読み取ってダウンロード
     */
    public function downloadFromFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            Log::error("指定されたファイルが見つかりません: {$filePath}");
            return [];
        }

        $urls = collect(file($filePath))
            ->map(fn($line) => trim($line))
            ->filter(fn($line) => !empty($line) && Str::startsWith($line, 'http'))
            ->values()
            ->all();

        return $this->downloadFromUrls($urls);
    }

    /**
     * 単一URLのダウンロードと保存
     */
    protected function downloadAndSave(string $url): ?string
    {
        Log::info("取得中: {$url}");

        try {
            $response = Http::get($url);
        } catch (\Exception $e) {
            Log::error("接続失敗: {$e->getMessage()}");
            return null;
        }

        if (!$response->successful()) {
            Log::error("HTTP取得失敗: {$url}");
            return null;
        }

        $html = $response->body();

        // titleから日付と店名を抽出
        if (preg_match('/<title>(.+?)<\/title>/u', $html, $matches)) {
            $title = html_entity_decode($matches[1]);

            // アナスロ形式: "YYYY/MM/DD 店舗名 データまとめ - アナスロ"
            if (preg_match('/(\d{4})\/(\d{1,2})\/(\d{1,2})\s+(.+?)\s+データまとめ\s*-\s*アナスロ/u', $title, $parts)) {
                $year = $parts[1];
                $month = str_pad($parts[2], 2, '0', STR_PAD_LEFT);
                $day = str_pad($parts[3], 2, '0', STR_PAD_LEFT);
                $date = $year . $month . $day;

                $storeName = preg_replace('/[\/:*?"<>|]/u', '_', $parts[4]);
                $filename = "{$date}_{$storeName}.html";
                $savePath = public_path("storage/store_data/{$filename}");

                if (!file_exists(dirname($savePath))) {
                    mkdir(dirname($savePath), 0755, true);
                }

                file_put_contents($savePath, $html);
                Log::info("保存完了: {$savePath}");
                return $savePath;
            }

            // みんレポ形式: "MM/DD 店舗名 | みんレポ"
            if (preg_match('/(\d{1,2})\/(\d{1,2})[^ ]*\s+(.+?)\s*\|/', $title, $parts)) {
                $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
                $day = str_pad($parts[2], 2, '0', STR_PAD_LEFT);
                $date = '2025' . $month . $day; // 固定年ならここを調整

                $storeName = preg_replace('/[\/:*?"<>|]/u', '_', $parts[3]);
                $filename = "{$date}_{$storeName}.html";
                $savePath = public_path("storage/store_data/{$filename}");

                if (!file_exists(dirname($savePath))) {
                    mkdir(dirname($savePath), 0755, true);
                }

                file_put_contents($savePath, $html);
                Log::info("保存完了: {$savePath}");
                return $savePath;
            }
        }

        if (!Str::contains($html, '</html>')) {
            Log::warning("HTML末尾タグが見つかりません: {$url}");
        } else {
            Log::warning("titleタグの解析に失敗: {$url}");
        }

        return null;
    }
}
