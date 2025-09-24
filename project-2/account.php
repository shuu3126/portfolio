<?php
require 'db.php';
session_start();

// **エラーメッセージ初期化**
$error = "";

// **ログイン処理**
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["email"]) && isset($_POST["password"])) {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user["password"])) {
        if ($user["verified"] == 0) {
            $_SESSION["pending_email"] = $user["email"];
            $_SESSION["email"] = $user["email"];

            // 💥 ここで未認証アカウントが削除されていないか確認
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND verified = 0");
            $stmt->execute([$email]);
            $stillExists = $stmt->fetch();

            if (!$stillExists) {
                // 🚨 もし未認証アカウントが削除されていたら、「もう一度登録する」ボタンを表示
                $error = '❌ このアカウントは認証されずに削除されました。もう一度登録してください。';
                $show_register_again_button = true;
            } else {
                $error = '❌ このアカウントはまだ認証されていません。';
                $show_verify_button = true;
            }
        } else {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user"] = $user["name"];
            $_SESSION["email"] = $user["email"];
            header("Location: account.php");
            exit();
        }
    }
}

// **ログインしていない場合はログイン画面を表示**
if (!isset($_SESSION["user_id"])) {
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>ログイン - FLUX PC</title>
        <link rel="stylesheet" href="css/login.css">
        <link rel="icon" type="logo.png" href="image/logo.png">
    </head>
    <body>
        <div class="logo">
            <img src="image/logo.png" alt="FLUX PC">
        </div>
        <div class="login-container">
            <h2>ログイン</h2>
            <form method="post">
                <input type="email" name="email" placeholder="メールアドレス" required>
                <input type="password" name="password" placeholder="パスワード" required>
                <button type="submit">ログイン</button>
            </form>

            <?php if (!empty($error)) echo "<p class='error'>{$error}</p>"; ?>

            <?php if (!empty($show_verify_button)): ?>
                <form action="verify.php" method="get">
                    <button type="submit" class="btn">▶ 認証ページへ進む</button>
                </form>
            <?php endif; ?>

            <p>アカウントをお持ちでないですか？ <a href="register.php">新規登録</a></p>
            <a href="index.php" class="top-button">TOPページへ戻る</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}


// **ログインユーザーの情報を取得**
$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["user"];
$user_email = $_SESSION["email"];

// **購入履歴を取得**
$stmt = $pdo->prepare("SELECT * FROM purchases WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$purchases = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マイページ - FLUX PC</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="icon" type="logo.png" href="image/logo.png">
</head>
<body>

<header>
    <h1>ようこそ、<?php echo htmlspecialchars($user_name); ?> さん！</h1>
    <a href="logout.php" class="logout-btn">ログアウト</a>
</header>

<div class="dashboard-container">
    <h2>マイページ</h2>
    <p>登録メール: <?php echo htmlspecialchars($user_email); ?></p>
    <p>アカウント情報の編集、注文履歴の確認ができます。</p>

    <hr>

    <!-- 🔹 購入履歴 -->
    <h3>購入履歴</h3>

    <?php if (count($purchases) > 0): ?>
        <table class="purchase-table">
            <thead>
                <tr>
                    <th>注文番号</th>
                    <th>購入日</th>
                    <th>合計金額</th>
                    <th>領収書</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($purchases as $purchase): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($purchase["transaction_id"]); ?></td>
                        <td><?php echo htmlspecialchars($purchase["created_at"]); ?></td>
                        <td>¥<?php echo number_format($purchase["total_price"]); ?></td>
                        <td><a href="receipts/<?php echo htmlspecialchars($purchase["transaction_id"]); ?>.pdf" target="_blank">ダウンロード</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>購入履歴がありません。</p>
    <?php endif; ?>

    <hr>

    <!-- 🔹 ゲスト購入履歴検索 -->
    <div class="guest-search">
        <h4>アカウント作成前に購入された方</h4>
        <form action="account.php" method="post">
            <input type="text" name="order_id" placeholder="注文番号を入力">
            <button type="submit">検索</button>
        </form>
    </div>

    <hr>

    <!-- 🔹 ゲスト注文番号をSQLの `users` テーブルと紐付け -->
    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST["order_id"])) {
        $order_id = trim($_POST["order_id"]);
        $stmt = $pdo->prepare("SELECT * FROM purchases WHERE transaction_id = ?");
        $stmt->execute([$order_id]);
        $purchase = $stmt->fetch();

        if ($purchase) {
            if (is_null($purchase["user_id"])) {
                // 🔗 ログインユーザーに紐付ける
                $updateStmt = $pdo->prepare("UPDATE purchases SET user_id = ? WHERE transaction_id = ?");
                $updateStmt->execute([$user_id, $order_id]);

                echo "<p>✅ 注文番号をアカウントに紐付けました。</p>";
            } else {
                echo "<p>ℹ️ この注文はすでにアカウントに紐付けられています。</p>";
            }

            // ✅ 購入履歴を再取得（← これが今回のポイント！）
            $stmt = $pdo->prepare("SELECT * FROM purchases WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            $purchases = $stmt->fetchAll();

            echo "<p>注文番号: " . htmlspecialchars($order_id) . "</p>";
            echo "<p><a href='receipts/" . htmlspecialchars($order_id) . ".pdf' target='_blank'>領収書をダウンロード</a></p>";
        } else {
            echo "<p>❌ 注文番号が見つかりませんでした。</p>";
        }
    }
    ?>

</div>
    <hr>
    <div class="back-to-top">
        <a href="index.php" class="top-button">トップページへ戻る</a>
    </div>

</body>
</html>
