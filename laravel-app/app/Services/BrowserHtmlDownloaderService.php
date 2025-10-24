<?php

namespace App\Services;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BrowserHtmlDownloaderService
{
    protected ?RemoteWebDriver $driver = null;

    public function __construct()
    {
        $this->startDriver();
    }

    public function __destruct()
    {
        $this->stopDriver();
    }

    /**
     * ブラウザドライバーを起動
     */
    protected function startDriver(): void
    {
        $userDataDir = sys_get_temp_dir() . '/chrome_' . getmypid() . '_' . uniqid();

        $options = new ChromeOptions();
        $options->setBinary('/usr/bin/google-chrome-stable');
        $options->addArguments([
            '--headless=new',
            '--disable-gpu',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-blink-features=AutomationControlled',
            '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.7390.122 Safari/537.36',
            "--user-data-dir={$userDataDir}",
        ]);

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        $this->driver = RemoteWebDriver::create(
            env('DUSK_DRIVER_URL', 'http://localhost:9515'),
            $capabilities,
            120000, // connection timeout
            120000  // request timeout
        );
    }

    /**
     * ブラウザドライバーを停止
     */
    protected function stopDriver(): void
    {
        if ($this->driver) {
            try {
                $this->driver->quit();
            } catch (\Exception $e) {
                Log::warning("[BrowserHtmlDownloader] ドライバー停止エラー: {$e->getMessage()}");
            }
            $this->driver = null;
        }
    }

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

            // リクエスト間隔を空ける（1-2秒）
            sleep(rand(1, 2));
        }

        return $savedFiles;
    }

    /**
     * テキストファイルからURLを読み取ってダウンロード
     */
    public function downloadFromFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            Log::error("[BrowserHtmlDownloader] 指定されたファイルが見つかりません: {$filePath}");
            return [];
        }

        $urls = collect(file($filePath))
            ->map(fn($line) => trim($line))
            ->filter(fn($line) => !empty($line) && Str::startsWith($line, 'http'))
            ->values()
            ->all();

        Log::info("[BrowserHtmlDownloader] {$filePath} から " . count($urls) . "件のURLを読み込みました");

        return $this->downloadFromUrls($urls);
    }

    /**
     * 単一URLのダウンロードと保存
     */
    protected function downloadAndSave(string $url): ?string
    {
        Log::info("[BrowserHtmlDownloader] 取得中: {$url}");

        try {
            $this->driver->get($url);

            // ページ読み込み待機
            sleep(2);

            $html = $this->driver->getPageSource();

            if (empty($html)) {
                Log::error("[BrowserHtmlDownloader] HTMLが空です: {$url}");
                return null;
            }

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
                    Log::info("[BrowserHtmlDownloader] 保存完了: {$savePath}");
                    return $savePath;
                }

                // みんレポ形式: "MM/DD 店舗名 | みんレポ"
                if (preg_match('/(\d{1,2})\/(\d{1,2})[^ ]*\s+(.+?)\s*\|/', $title, $parts)) {
                    $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
                    $day = str_pad($parts[2], 2, '0', STR_PAD_LEFT);
                    $date = '2025' . $month . $day; // 年は適宜調整

                    $storeName = preg_replace('/[\/:*?"<>|]/u', '_', $parts[3]);
                    $filename = "{$date}_{$storeName}.html";
                    $savePath = public_path("storage/store_data/{$filename}");

                    if (!file_exists(dirname($savePath))) {
                        mkdir(dirname($savePath), 0755, true);
                    }

                    file_put_contents($savePath, $html);
                    Log::info("[BrowserHtmlDownloader] 保存完了: {$savePath}");
                    return $savePath;
                }
            }

            if (!Str::contains($html, '</html>')) {
                Log::warning("[BrowserHtmlDownloader] HTML末尾タグが見つかりません: {$url}");
            } else {
                Log::warning("[BrowserHtmlDownloader] titleタグの解析に失敗: {$url}");
            }

            return null;

        } catch (\Exception $e) {
            Log::error("[BrowserHtmlDownloader] 取得失敗: {$url}, エラー: {$e->getMessage()}");
            return null;
        }
    }
}
