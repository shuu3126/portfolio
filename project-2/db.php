<?php
$host = "localhost"; // エックスサーバーなら "localhost"
$dbname = "fluxpc_db"; // データベース名
$username = "fluxpc_user1"; // MySQLユーザー名
$password = "sadakata126"; // MySQLパスワード（セキュリティのため.env管理推奨）

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("データベース接続エラー: " . $e->getMessage(), 3, __DIR__ . "/error_log.txt");
    die("データベース接続に失敗しました。管理者にお問い合わせください。");
}
?>
