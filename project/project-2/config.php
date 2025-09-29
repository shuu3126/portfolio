<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable('/home/fluxpc/fluxpc.jp'); 
$dotenv->load();

// **データベース接続情報**
define('DB_DSN', $_ENV['DB_DSN'] ?? 'mysql:host=localhost;dbname=fluxpc;charset=utf8');
define('DB_USER', $_ENV['DB_USER'] ?? 'fluxpc_user1');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? 'sadakata126');

// **Stripe APIキーを.envから取得**
define('STRIPE_SECRET_KEY', $_ENV['STRIPE_SECRET_KEY'] ?? '');
define('STRIPE_PUBLIC_KEY', $_ENV['STRIPE_PUBLIC_KEY'] ?? '');

// **エラーレポート設定**
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // **PDOでDB接続**
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("データベース接続に失敗しました: " . $e->getMessage());
}
?>
