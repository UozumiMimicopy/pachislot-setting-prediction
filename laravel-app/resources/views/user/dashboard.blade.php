<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-2xl font-bold mb-4">パチスロ設定予想サイトへようこそ</h3>
                    <p class="mb-6">地域のパチスロ店舗の設定予想を閲覧・投稿できます。</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <!-- Quick Stats -->
                        <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white p-6 rounded-lg shadow-lg">
                            <h4 class="text-lg font-semibold mb-2">店舗数</h4>
                            <p class="text-4xl font-bold">0</p>
                            <p class="text-sm opacity-80">登録店舗数</p>
                        </div>

                        <div class="bg-gradient-to-br from-green-500 to-green-600 text-white p-6 rounded-lg shadow-lg">
                            <h4 class="text-lg font-semibold mb-2">予想投稿数</h4>
                            <p class="text-4xl font-bold">0</p>
                            <p class="text-sm opacity-80">総予想投稿数</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <h4 class="text-xl font-semibold">メニュー</h4>

                        <a href="#" class="block p-6 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                            <h5 class="text-lg font-semibold mb-2">店舗検索</h5>
                            <p class="text-sm text-gray-600 dark:text-gray-400">お近くのパチスロ店舗を検索</p>
                        </a>

                        <a href="#" class="block p-6 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                            <h5 class="text-lg font-semibold mb-2">設定予想一覧</h5>
                            <p class="text-sm text-gray-600 dark:text-gray-400">最新の設定予想をチェック</p>
                        </a>

                        <a href="#" class="block p-6 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                            <h5 class="text-lg font-semibold mb-2">マイページ</h5>
                            <p class="text-sm text-gray-600 dark:text-gray-400">自分の予想履歴を確認</p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
