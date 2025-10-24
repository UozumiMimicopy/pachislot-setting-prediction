<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Models\MasterStore;
use App\Models\ProcessLogs;
use Carbon\Carbon;
use Throwable;

enum ErrorReason: string
{
    case AbnormalSlotCount = '異常台数';
    case AbnormalSlotData = '異常データ';
    case HtmlDateMismatch = '日付不一致';
    case HtmlParseFailure = 'HTML解析失敗';
    case CsvConversionFailure = 'CSV変換失敗';
    case Unknown = '不明なエラー';
}

class StoreManualService
{
    protected HtmlParsingService $htmlParsingService;
    protected AnasuroParsingService $anasuroParsingService;
    protected DataTransformationService $dataTransformationService;
    protected DataStorageService $dataStorageService;
    protected ErrorAnalysisService $errorAnalysisService;
    protected FileManagementService $fileManagementService;

    protected string $storeDataPath;
    protected string $archiveDataPath;
    protected string $errorDataPath;
    protected string $duplicateDataPath;

    public function __construct(
        HtmlParsingService $htmlParsingService,
        AnasuroParsingService $anasuroParsingService,
        DataTransformationService $dataTransformationService,
        DataStorageService $dataStorageService,
        ErrorAnalysisService $errorAnalysisService,
        FileManagementService $fileManagementService
    ) {
        $this->htmlParsingService = $htmlParsingService;
        $this->anasuroParsingService = $anasuroParsingService;
        $this->dataTransformationService = $dataTransformationService;
        $this->dataStorageService = $dataStorageService;
        $this->errorAnalysisService = $errorAnalysisService;
        $this->fileManagementService = $fileManagementService;

        $this->storeDataPath      = storage_path("app/public/store_data/");
        $this->archiveDataPath    = storage_path("app/public/archive_store_data/");
        $this->errorDataPath      = storage_path("app/public/error_store_data/");
        $this->duplicateDataPath  = storage_path("app/public/duplicate_store_data/");
    }

    public function execute(): void
    {
        $files = $this->getTargetFiles();
        Log::info("データ処理開始: " . count($files) . " 個のファイルを検出");

        foreach ($files as $file) {
            Log::info("処理対象ファイル: {$file}");
            $this->processFile($file);
        }

        Log::info("データ処理終了");
    }

    protected function getTargetFiles(): array
    {
        $files = array_filter(scandir($this->storeDataPath), fn($file) => $file !== '.' && $file !== '..');

        // 日付昇順（ファイル名昇順）でソート
        usort($files, function ($a, $b) {
            return strcmp($a, $b);
        });

        return $files;
    }

    protected function processFile(string $file): void
    {
        if (!$this->fileManagementService->validateFileName($file)) {
            Log::warning("ファイル名エラー: {$file}");
            return;
        }

        if (!preg_match('/^(\d{8})_(.+)\.html$/', $file, $matches)) {
            Log::warning("ファイル名のフォーマットが正しくありません: {$file}");
            return;
        }

        [$date, $storeName] = [$matches[1], $matches[2]];
        $logDate = Carbon::createFromFormat('Ymd', $date)->toDateString();

        $storeId = MasterStore::where('name', $storeName)->value('id');
        if (!$storeId) {
            Log::warning("店舗情報取得失敗: {$storeName}");
            return;
        }

        Log::info("処理対象: 店舗ID:{$storeId}, 日付:{$date}");

        if (ProcessLogs::isSuccessfullyProcessed($storeId, $logDate)) {
            Log::error("処理済みデータ: [店舗ID:{$storeId}, 日付:{$date}]");
            $this->moveFile($file, $this->duplicateDataPath);
            return;
        }

        ProcessLogs::markProcessing($storeId, $logDate);
        Log::info("処理開始: [店舗ID:{$storeId}, 日付:{$date}]");

        DB::beginTransaction();

        try {
            $htmlPath = $this->storeDataPath . $file;
            $htmlContent = file_get_contents($htmlPath);

            // HTMLの内容からアナスロかみんレポか判定
            $isAnasuro = $this->isAnasuroHtml($htmlContent);
            Log::info("HTMLソース判定: " . ($isAnasuro ? 'アナスロ' : 'みんレポ'));

            // 適切なパーサーを使用
            $parsedData = $isAnasuro
                ? $this->anasuroParsingService->parseHtml($htmlContent)
                : $this->htmlParsingService->parseHtml($htmlContent);

            if (!$parsedData || substr($date, 4, 4) !== $parsedData['date']) {
                throw new \Exception(ErrorReason::HtmlDateMismatch->value);
            }

            $processedData = $this->dataTransformationService->transformToDbObjects($parsedData, $logDate);

            if (!$processedData) {
                throw new \Exception(ErrorReason::CsvConversionFailure->value);
            }

            $this->dataStorageService->storeData($processedData);

            $errorReasons = $this->errorAnalysisService->analyzeAndGenerateReport($storeId, $logDate);

            if (!empty($errorReasons)) {
                throw new \Exception($errorReasons[0]);
            }

            ProcessLogs::markCompleted($storeId, $logDate);
            DB::commit();

            Log::info("処理成功: [店舗ID:{$storeId}, 日付:{$date}]");
            $this->moveFile($file, $this->archiveDataPath);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error("保存処理失敗: " . $e->getMessage());
            $this->handleError($file, ErrorReason::tryFrom($e->getMessage()) ?? ErrorReason::Unknown);
            ProcessLogs::markError($storeId, $logDate);
        }
    }

    protected function moveFile(string $file, string $destination): void
    {
        $source = $this->storeDataPath . $file;
        $target = $destination . $file;

        if (rename($source, $target)) {
            Log::info("ファイル移動成功: {$file} → " . basename($destination));
        } else {
            Log::error("ファイル移動失敗: {$file}");
        }
    }

    protected function handleError(string $file, ErrorReason $reason): void
    {
        Log::error("{$reason->value}: {$file}");
        $this->moveFile($file, $this->errorDataPath);
    }

    /**
     * HTMLがアナスロかどうかを判定
     *
     * @param string $html
     * @return bool
     */
    protected function isAnasuroHtml(string $html): bool
    {
        // titleタグに「アナスロ」が含まれているかで判定
        if (preg_match('/<title>.*?アナスロ.*?<\/title>/u', $html)) {
            return true;
        }

        // URLコメントに「ana-slo.com」が含まれているかで判定
        if (preg_match('/saved from url=.*?ana-slo\.com/i', $html)) {
            return true;
        }

        return false;
    }
}
