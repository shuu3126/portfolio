<?php
session_start();

// デバッグ用ログ
error_log("[INFO] success.php - session started.");
error_log("[INFO] success.php - session data: " . json_encode($_SESSION));

// データベース接続
require 'db.php';

// **ログインユーザーのIDを取得（ゲストの場合はNULL）**
$user_id = $_SESSION['user_id'] ?? null;

// **注文番号（`transaction_id`）をセッションから取得**
$transaction_id = $_SESSION['transaction_id'] ?? null;

// **`transaction_id` がまだない場合、データベースから検索**
if (!$transaction_id) {
    error_log("[ERROR] success.php - transaction_id が見つかりません。データベースから検索します。");

    $stmt = $pdo->prepare("SELECT transaction_id FROM purchases ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    $latest_purchase = $stmt->fetch();

    if ($latest_purchase) {
        $transaction_id = $latest_purchase['transaction_id'];
        $_SESSION['transaction_id'] = $transaction_id;
        error_log("[INFO] success.php - データベースから取得した transaction_id: " . $transaction_id);
    } else {
        error_log("[ERROR] success.php - transaction_id の取得に失敗しました。");
        $transaction_id = null;
    }
}

// **カートの情報を取得**
$total_price = 0;
$items = [];

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $total_price += $item['price'] * $item['quantity'];
        $items[] = $item['name'] . ' x ' . $item['quantity'];
    }
} else {
    error_log("[WARNING] success.php - カートが空です。データベースから取得を試みます。");

    $stmt = $pdo->prepare("SELECT total_price, items FROM purchases WHERE transaction_id = ? LIMIT 1");
    $stmt->execute([$transaction_id]);
    $purchase = $stmt->fetch();

    if ($purchase) {
        $total_price = $purchase['total_price'];
        $items = json_decode($purchase['items'], true);
        error_log("[INFO] success.php - データベースから取得した total_price: " . $total_price);
    } else {
        error_log("[ERROR] success.php - total_price と items が取得できませんでした。");
    }
}

// **購入データが未登録の場合のみ登録**
$stmt = $pdo->prepare("SELECT COUNT(*) FROM purchases WHERE transaction_id = ?");
$stmt->execute([$transaction_id]);
$exists = $stmt->fetchColumn();

if ($exists == 0) {
    // 登録されていない場合は新しく保存
    $stmt = $pdo->prepare("INSERT INTO purchases (user_id, transaction_id, total_price, items, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, $transaction_id, $total_price, json_encode($items)]);
    error_log("[INFO] success.php - 新規購入情報を保存: $transaction_id");
} elseif ($user_id) {
    // ゲスト購入だったものをログインユーザーと紐づける
    $stmt = $pdo->prepare("UPDATE purchases SET user_id = ? WHERE transaction_id = ? AND user_id IS NULL");
    $stmt->execute([$user_id, $transaction_id]);
    error_log("[INFO] success.php - ゲスト購入をログインユーザーに紐付け: $transaction_id -> user_id=$user_id");
} else {
    error_log("[INFO] success.php - 購入情報は既に存在: $transaction_id");
}

// **購入完了後、カートをクリア**
unset($_SESSION['cart']);
unset($_SESSION['stripe_session_id']);

// **領収書のパスを設定**
$receipt_url = "https://fluxpc.jp/receipts/" . $transaction_id . ".pdf";

// **領収書の存在を確認**
$receipt_path = __DIR__ . "/receipts/" . basename($receipt_url);
if (!file_exists($receipt_path)) {
    error_log("[ERROR] success.php - Receipt file does not exist: " . $receipt_path);
    $receipt_url = null;
} elseif (!is_readable($receipt_path)) {
    error_log("[ERROR] success.php - Receipt file is not readable (permission error): " . $receipt_path);
    $receipt_url = null;
} else {
    error_log("[SUCCESS] success.php - Receipt file is accessible: " . $receipt_path);
}
?>


<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>購入完了</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 400px;
        }
        .success-icon {
            font-size: 50px;
            color: #28a745;
            margin-bottom: 10px;
        }
        h1 {
            color: #28a745;
            font-size: 24px;
        }
        p {
            font-size: 16px;
            color: #333;
            margin-bottom: 20px;
        }
        .receipt-link {
            display: inline-block;
            padding: 12px 20px;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            background-color: #007bff;
            color: white;
            border-radius: 5px;
            transition: background 0.3s ease;
        }
        .receipt-link:hover {
            background-color: #0056b3;
        }
        .back-link {
            display: block;
            margin-top: 20px;
            font-size: 14px;
            text-decoration: none;
            color: #007bff;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="success-icon">✅</div>
    <h1>ご購入ありがとうございます！</h1>
    <p>決済が完了しました。</p>

    <?php if ($receipt_url): ?>
        <a class="receipt-link" href="<?= htmlspecialchars($receipt_url) ?>" target="_blank">📄 領収書をダウンロード</a>
    <?php else: ?>
        <p style="color: red;">⚠ 領収書が見つかりませんでした。</p>
        <p>お手数ですが、サポートまでご連絡ください。</p>
    <?php endif; ?>

    <a class="back-link" href="index.php">🏠 トップページに戻る</a>
</div>

</body>
</html>
