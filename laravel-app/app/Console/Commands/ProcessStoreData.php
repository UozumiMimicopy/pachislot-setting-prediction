<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\StoreManualService;

class ProcessStoreData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'store:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'storage/app/public/store_data/ のHTMLファイルを処理してDBに保存';

    /**
     * Execute the console command.
     */
    public function handle(StoreManualService $storeManualService)
    {
        $this->info('📦 店舗データ処理を開始します...');
        $this->line('');

        try {
            $storeManualService->execute();

            $this->line('');
            $this->info('✅ 処理が完了しました');
            return 0;
        } catch (\Exception $e) {
            $this->line('');
            $this->error('❌ エラーが発生しました: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}
