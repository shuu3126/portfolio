<?php
session_start();
require_once __DIR__ . '/config.php';

// ✅ データベース接続
try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("データベース接続に失敗しました: " . $e->getMessage());
}

// ✅ カートの中身を取得（セッション）
$cart = $_SESSION['cart'] ?? [];

// 商品削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_id'])) {
    $remove_id = $_POST['remove_id'];
    if (isset($cart[$remove_id])) {
        unset($cart[$remove_id]);
        $_SESSION['cart'] = $cart;
    }
    header("Location: cart.php");
    exit;
}

// ✅ 合計金額を計算
$total_price = 0;
foreach ($cart as $item) {
    $total_price += $item['price'] * $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ショッピングカート</title>
    <link rel="icon" type="logo.png" href="image/logo.png">
    <link rel="stylesheet" href="css/c1.css">
</head>
<body>
    <header>
        <a href="index.php">
            <img src="image/logo.png" alt="ロゴ" class="logo">
        </a>
    </header>
<div id="cart-container">
    <h1>🛒 ショッピングカート</h1>

    <?php if (empty($cart)): ?>
        <p>カートに商品がありません。</p>
        <a href="index.php">🏠 買い物を続ける</a>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>商品画像</th>
                    <th>商品名</th>
                    <th>数量</th>
                    <th>価格</th>
                    <th>小計</th>
                    <th>削除</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cart as $id => $item): ?>
                    <tr>
                        <td>
                            <?php
                            // ✅ データベースから商品画像を取得
                            $stmt = $pdo->prepare("SELECT image FROM products WHERE name = :name");
                            $stmt->bindValue(':name', $item['name'], PDO::PARAM_STR);
                            $stmt->execute();
                            $result = $stmt->fetch();
                            
                            // 画像URLを設定（デフォルト画像を用意）
                            $image_url = !empty($result['image']) ? htmlspecialchars($result['image'], ENT_QUOTES, 'UTF-8') : 'image/default.png';
                            ?>
                            <img src="<?= $image_url ?>" alt="商品画像" width="80">
                        </td>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td>
                            <input type="number" class="quantity-input" value="<?= htmlspecialchars($item['quantity']) ?>" min="1" data-id="<?= $id ?>">
                        </td>
                        <td class="cart-item-price">¥<?= number_format($item['price']) ?></td>
                        <td class="cart-item-total">¥<?= number_format($item['price'] * $item['quantity']) ?></td>
                        <td>
                            <form action="cart.php" method="post">
                                <input type="hidden" name="remove_id" value="<?= $id ?>">
                                <button type="submit" class="remove-btn">❌</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- 合計金額 -->
        <div id="total-price">
            <p>合計金額: <strong>¥<?= number_format($total_price) ?></strong></p>
        </div>

        <!-- チェックアウトボタン -->
        <div class="checkout-container">
            <form action="checkout.php" method="post">
                <button id="checkout-btn">購入手続きへ進む</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script src="cart.js"></script>

</body>
</html>
