<?php
require_once __DIR__ . '/config.php';  // DB接続情報を読み込む
session_start();  // セッションを開始（カート機能のため）

// ✅ DB接続
try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("データベース接続に失敗しました: " . $e->getMessage());
}

// ✅ 商品IDを取得
$product_id = $_GET['id'] ?? null;
if (!$product_id) {
    die("❌ エラー: 商品IDが指定されていません");
}

// ✅ 商品情報を取得
$stmt = $pdo->prepare("SELECT id, name, description, price, image, details, stripe_url FROM products WHERE id = :id");
$stmt->bindValue(':id', $product_id, PDO::PARAM_INT);
$stmt->execute();
$product = $stmt->fetch();

if (!$product) {
    die("❌ エラー: 指定された商品が見つかりません");
}

// ✅ 商品の詳細リストを配列として取得
$product_details = explode("\n", $product['details']); // データベースに改行区切りで保存されている場合
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - 商品詳細</title>

    <!-- スタイルシートの読み込み -->
    <link rel="stylesheet" href="css/c1.css">
    <link rel="stylesheet" href="css/sp.css">
    <link rel="icon" href="image/logo.png" type="image/x-icon">
</head>
<body>
    <header>
        <a href="index.php">
            <img src="image/logo.png" alt="ロゴ" class="logo">
        </a>
        <h1>商品詳細</h1>
    </header>

    <main>
        <div id="product-container">
            <div class="product-image">
                <img id="product-image" src="<?= htmlspecialchars($product['image']) ?>" alt="商品画像">
            </div>
            <div class="product-info">
                <h2 id="product-title"><?= htmlspecialchars($product['name']) ?></h2>
                <p id="product-description"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                <ul id="product-details">
                    <?php foreach ($product_details as $detail): ?>
                        <li><?= htmlspecialchars($detail) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p id="product-price">価格: ¥<?= number_format($product['price']) ?></p>
            </div>

                <!-- ✅ カートに追加（JavaScriptなし、PHPで処理） -->
                <form action="add-to-cart.php" method="post">
                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($product_id) ?>">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" id="add-to-cart-btn">🛒 カートに追加</button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
