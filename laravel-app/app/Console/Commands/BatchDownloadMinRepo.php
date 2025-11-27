<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\HtmlDownloaderService;

class BatchDownloadMinRepo extends Command
{
    protected $signature = 'minrepo:batch-download {url?} {--file=} {--use-browser : ブラウザ自動化を使用してBot検知を回避}';
    protected $description = 'みんレポの指定URLまたはURL一覧ファイルからHTMLをダウンロード保存する';

    public function handle()
    {
        $url = $this->argument('url');
        $file = $this->option('file');
        $useBrowser = $this->option('use-browser');

        // サービスの選択（ブラウザ版 or HTTP版）
        if ($useBrowser) {
            $this->info("🌐 ブラウザ自動化モードでHTMLダウンロードを実行します");
            $downloader = app(\App\Services\BrowserHtmlDownloaderService::class);
        } else {
            $this->info("📡 HTTP通信モードでHTMLダウンロードを実行します");
            $downloader = app(HtmlDownloaderService::class);
        }

        $savedFiles = [];

        if ($file) {
            if (!file_exists($file)) {
                $this->error("指定されたファイルが見つかりません: {$file}");
                return 1;
            }

            $savedFiles = $downloader->downloadFromFile($file);
        } elseif ($url) {
            $savedFiles = $downloader->downloadFromUrls([$url]);
        } else {
            $this->error("URL引数または --file オプションのいずれかを指定してください。");
            return 1;
        }

        $this->info("✅ 全URLの処理が完了しました。保存済み: " . count($savedFiles) . " 件");
        foreach ($savedFiles as $filePath) {
            $this->line("- {$filePath}");
        }

        return 0;
    }
}
