<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Developer Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-2xl font-bold mb-4">開発者ダッシュボード</h3>
                    <p class="mb-6">システム管理と開発ツールにアクセスできます。</p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Statistics Card -->
                        <div class="bg-blue-100 dark:bg-blue-900 p-6 rounded-lg">
                            <h4 class="text-lg font-semibold mb-2">ユーザー統計</h4>
                            <p class="text-3xl font-bold">{{ \App\Models\User::count() }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">総ユーザー数</p>
                        </div>

                        <!-- System Info Card -->
                        <div class="bg-green-100 dark:bg-green-900 p-6 rounded-lg">
                            <h4 class="text-lg font-semibold mb-2">システム情報</h4>
                            <p class="text-sm">Laravel {{ app()->version() }}</p>
                            <p class="text-sm">PHP {{ PHP_VERSION }}</p>
                        </div>

                        <!-- Database Info Card -->
                        <div class="bg-purple-100 dark:bg-purple-900 p-6 rounded-lg">
                            <h4 class="text-lg font-semibold mb-2">データベース</h4>
                            <p class="text-sm">{{ config('database.default') }}</p>
                            <p class="text-sm">{{ config('database.connections.mysql.database') }}</p>
                        </div>
                    </div>

                    <div class="mt-8">
                        <h4 class="text-xl font-semibold mb-4">管理機能</h4>
                        <div class="space-y-2">
                            <a href="#" class="block p-4 bg-gray-100 dark:bg-gray-700 rounded hover:bg-gray-200 dark:hover:bg-gray-600">
                                ユーザー管理
                            </a>
                            <a href="#" class="block p-4 bg-gray-100 dark:bg-gray-700 rounded hover:bg-gray-200 dark:hover:bg-gray-600">
                                システム設定
                            </a>
                            <a href="#" class="block p-4 bg-gray-100 dark:bg-gray-700 rounded hover:bg-gray-200 dark:hover:bg-gray-600">
                                ログ閲覧
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
