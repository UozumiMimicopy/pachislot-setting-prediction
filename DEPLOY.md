# ロリポップへのデプロイ手順書

## 前提条件

### 必要なロリポッププラン
- **スタンダードプラン以上** を推奨
- SSH接続が可能なプラン
- Composer実行が可能なプラン
- PHP 8.2以上
- MySQL 5.7以上

### 準備するもの
- ロリポップのアカウント情報
- FTPまたはSSH接続情報
- データベース作成権限

---

## ステップ1: ロリポップ管理画面での準備

### 1.1 データベースの作成

1. ロリポップ管理画面にログイン
2. 「サーバーの管理・設定」→「データベース」
3. 「新規作成」をクリック
4. 以下の情報をメモ：
   - データベース名（例: `LAA1234567-pachislot`）
   - ユーザー名（例: `LAA1234567`）
   - パスワード
   - サーバー名（例: `mysql123.lolipop.jp`）

### 1.2 SSH接続の有効化

1. 「サーバーの管理・設定」→「SSH」
2. SSH接続を有効化
3. 接続情報をメモ：
   - サーバー名（例: `ssh123.lolipop.jp`）
   - アカウント名
   - パスワード

### 1.3 PHPバージョンの設定

1. 「サーバーの管理・設定」→「PHP設定」
2. 対象ドメインを選択
3. **PHP 8.2以上** を選択して保存

### 1.4 ドメイン設定

1. 「サーバーの管理・設定」→「独自ドメイン設定」
2. 使用するドメイン/サブドメインを確認
3. 公開フォルダを後で変更するためメモしておく

---

## ステップ2: ローカルでの準備（完了済み）

以下の作業は既に完了しています：
- ✅ `.env.production` テンプレート作成
- ✅ アセットのビルド（`npm run build`）

---

## ステップ3: ファイルのアップロード

### 方法A: Git経由（推奨）

#### 3.1 SSHでロリポップに接続
```bash
ssh アカウント名@ssh123.lolipop.jp
```

#### 3.2 プロジェクトディレクトリに移動
```bash
cd ~/
```

#### 3.3 リポジトリをクローン
```bash
git clone https://github.com/UozumiMimicopy/pachislot-setting-prediction.git
cd pachislot-setting-prediction/laravel-app
```

### 方法B: FTP経由

FTPクライアント（FileZilla等）で以下のファイル・フォルダをアップロード：
- `laravel-app/` 配下のすべてのファイル

**注意**: `.git` や `node_modules` は不要（容量削減のため除外）

---

## ステップ4: 環境設定ファイルの作成

### 4.1 .envファイルの作成

SSHで接続した状態で：

```bash
cd ~/pachislot-setting-prediction/laravel-app
cp .env.production .env
nano .env  # またはviで編集
```

### 4.2 .envの編集内容

以下の項目を実際の値に置き換える：

```env
APP_NAME="パチスロ設定予想"
APP_ENV=production
APP_KEY=  # 後でphp artisan key:generateで生成
APP_DEBUG=false
APP_URL=https://your-actual-domain.com  # 実際のドメインに変更

DB_CONNECTION=mysql
DB_HOST=mysql123.lolipop.jp  # ロリポップのDBホスト
DB_PORT=3306
DB_DATABASE=LAA1234567-pachislot  # 作成したDB名
DB_USERNAME=LAA1234567  # DBユーザー名
DB_PASSWORD=your_db_password  # DBパスワード

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

---

## ステップ5: Composerでの依存関係インストール

```bash
cd ~/pachislot-setting-prediction/laravel-app

# Composerがない場合はインストール
curl -sS https://getcomposer.org/installer | php
mv composer.phar composer

# 依存関係をインストール（開発用パッケージは除外）
php composer install --no-dev --optimize-autoloader
```

**注意**: ロリポップの環境によっては `php` コマンドが使えない場合があります。
その場合は `php8.2` や `/usr/bin/php8.2` など、バージョン付きコマンドを使用してください。

---

## ステップ6: Laravelのセットアップ

### 6.1 アプリケーションキーの生成
```bash
php artisan key:generate
```

### 6.2 ストレージディレクトリのシンボリックリンク作成
```bash
php artisan storage:link
```

### 6.3 ストレージディレクトリのパーミッション設定
```bash
chmod -R 775 storage bootstrap/cache
```

### 6.4 データベースマイグレーション実行
```bash
php artisan migrate --force
```

### 6.5 開発者アカウントの作成
```bash
php artisan db:seed --class=DeveloperUserSeeder --force
```

### 6.6 キャッシュの最適化
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## ステップ7: Webサーバー設定

### 7.1 公開フォルダの変更

ロリポップ管理画面で：
1. 「サーバーの管理・設定」→「独自ドメイン設定」
2. 対象のドメインを選択
3. 公開（アップロード）フォルダを以下に変更：
   ```
   /pachislot-setting-prediction/laravel-app/public
   ```

### 7.2 .htaccessの確認

`public/.htaccess` が存在することを確認。存在しない場合は以下の内容で作成：

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

---

## ステップ8: 動作確認

### 8.1 サイトへのアクセス
ブラウザで設定したドメインにアクセス：
```
https://your-domain.com
```

ウェルカムページが表示されることを確認。

### 8.2 ログイン確認

#### テストユーザー（一般）
- URL: `https://your-domain.com/login`
- Email: `test@example.com`
- Password: `password`
- リダイレクト先: `/user/dashboard`

#### 開発者アカウント
- URL: `https://your-domain.com/login`
- Email: `uozmui.engineer@gmail.com`
- Password: `developer123`
- リダイレクト先: `/developer/dashboard`

### 8.3 ダッシュボード確認
- 利用者用ダッシュボード: パチスロ設定予想の機能が表示される
- 開発者用ダッシュボード: システム統計と管理機能が表示される

---

## トラブルシューティング

### エラー: 500 Internal Server Error
1. ストレージのパーミッションを確認
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```
2. `.env` ファイルが正しく設定されているか確認
3. エラーログを確認
   ```bash
   tail -f storage/logs/laravel.log
   ```

### エラー: Database connection failed
1. `.env` のDB設定を確認
2. ロリポップ管理画面でDB情報が正しいか確認
3. DBサーバーへの接続テスト
   ```bash
   mysql -h mysql123.lolipop.jp -u LAA1234567 -p
   ```

### エラー: Composer command not found
1. Composerを手動インストール
   ```bash
   curl -sS https://getcomposer.org/installer | php
   mv composer.phar composer
   ./composer install --no-dev --optimize-autoloader
   ```

### ページが真っ白
1. `APP_DEBUG=true` に変更してエラー内容を確認
2. 確認後は必ず `APP_DEBUG=false` に戻す

---

## セキュリティチェックリスト

- [ ] `APP_DEBUG=false` になっているか
- [ ] `APP_ENV=production` になっているか
- [ ] 開発者アカウントのパスワードを変更したか
- [ ] SSL証明書（HTTPS）が有効になっているか
- [ ] `.env` ファイルがWeb公開されていないか（publicフォルダ外に配置）
- [ ] ストレージディレクトリのパーミッションが適切か（775または755）

---

## 定期メンテナンス

### アプリケーションの更新
```bash
cd ~/pachislot-setting-prediction
git pull origin master
cd laravel-app
php composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### ログのクリア
```bash
php artisan log:clear  # カスタムコマンドの場合
# または手動で
rm storage/logs/*.log
```

---

## サポート情報

問題が発生した場合は、GitHub Issuesで報告してください：
https://github.com/UozumiMimicopy/pachislot-setting-prediction/issues
