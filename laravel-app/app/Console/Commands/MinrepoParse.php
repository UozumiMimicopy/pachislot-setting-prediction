<?php

namespace App\Console\Commands;

use App\Models\MinrepoData;
use App\Services\MinrepoParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MinrepoParse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'minrepo:parse {file_path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parse minrepo HTML file and save to database';

    /**
     * Execute the console command.
     */
    public function handle(MinrepoParser $parser)
    {
        $filePath = $this->argument('file_path');

        $this->info("Parsing: {$filePath}");

        try {
            // HTMLをパース
            $data = $parser->parse($filePath);

            $this->info("Store: {$data['store_name']}");
            $this->info("Date: {$data['date']->format('Y-m-d')}");
            $this->info("Machines: " . count($data['machines']));

            // 店舗IDを取得
            $storeId = $parser->getStoreId($data['store_name']);
            if (!$storeId) {
                $this->error("Store not found in master_stores: {$data['store_name']}");
                return 1;
            }

            $this->info("Store ID: {$storeId}");

            // データベースに保存
            $savedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            DB::beginTransaction();

            try {
                foreach ($data['machines'] as $machine) {
                    // 機種IDを取得
                    $modelId = $parser->getModelId($machine['model_name']);

                    if (!$modelId) {
                        $this->warn("Model not found: {$machine['model_name']} - Skipping");
                        $skippedCount++;
                        continue;
                    }

                    try {
                        // データを保存 (重複はスキップ)
                        MinrepoData::updateOrCreate(
                            [
                                'store_id' => $storeId,
                                'model_id' => $modelId,
                                'date' => $data['date']->format('Y-m-d'),
                                'machine_number' => $machine['machine_number'],
                            ],
                            [
                                'differential_medals' => $machine['differential_medals'],
                                'game_count' => $machine['game_count'],
                                'payout_rate' => $machine['payout_rate'],
                            ]
                        );

                        $savedCount++;
                    } catch (\Exception $e) {
                        $this->error("Error saving data: {$e->getMessage()}");
                        $errorCount++;
                    }
                }

                DB::commit();

                $this->info("✅ Saved: {$savedCount} records");
                $this->info("⏭️  Skipped: {$skippedCount} records (model not found)");
                if ($errorCount > 0) {
                    $this->error("❌ Errors: {$errorCount} records");
                }

                return 0;
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Transaction failed: {$e->getMessage()}");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("Parse error: {$e->getMessage()}");
            return 1;
        }
    }
}
