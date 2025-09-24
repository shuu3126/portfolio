<?php
session_start();
require 'config.php'; // DB接続設定

// ✅ 商品IDと数量を取得
$product_id = $_POST['product_id'] ?? null;
$quantity = $_POST['quantity'] ?? 1;

if (!$product_id) {
    die("❌ エラー: 商品IDが指定されていません。");
}

// ✅ 商品情報をDBから取得
$stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id = :id");
$stmt->bindValue(':id', $product_id, PDO::PARAM_INT);
$stmt->execute();
$product = $stmt->fetch();

if (!$product) {
    die("❌ エラー: 指定された商品が見つかりません。（ID: $product_id）");
}

// ✅ セッションにカート情報を保存
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// 既にカートに商品がある場合は数量を更新
if (isset($_SESSION['cart'][$product_id])) {
    $_SESSION['cart'][$product_id]['quantity'] += $quantity;
} else {
    $_SESSION['cart'][$product_id] = [
        'name' => $product['name'],
        'price' => $product['price'],
        'quantity' => $quantity
    ];
}

// ✅ カートページにリダイレクト
header("Location: cart.php");
exit;
?>
