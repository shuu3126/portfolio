<?php
require 'db.php';

// **セッション設定**
session_save_path(__DIR__ . "/tmp");
session_start();
session_regenerate_id(true); // セキュリティ強化

error_reporting(E_ALL);
ini_set('display_errors', 1);

// **5分経過した未認証アカウントを削除**
$stmt = $pdo->prepare("
    DELETE FROM users 
    WHERE verified = 0 
    AND created_at < (NOW() - INTERVAL 5 MINUTE)
");
$stmt->execute();

// **メール送信関数**
function sendVerificationEmail($email, $verification_code) {
    $subject = "【FLUX.PC】メール認証コード";
    $message = "以下の認証コードを入力してください: " . $verification_code;
    
    // **エックスサーバー用のメールヘッダー設定**
    $headers = "From: FLUX.PC <info@fluxpc.jp>\r\n";
    $headers .= "Reply-To: info@fluxpc.jp\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    if (!mail($email, $subject, $message, $headers)) {
        error_log("❌ メール送信エラー: " . error_get_last()['message']); // エラーログに記録
        return false;
    }
    return true;
}

// **POSTリクエストを処理**
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $verification_code = mt_rand(100000, 999999); // 6桁の認証コード

    // **メールがすでに登録されているか確認**
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        echo "❌ このメールアドレスはすでに登録されています。";
        exit();
    }

    // **ユーザーをデータベースに追加**
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, phone, password, verification_code, verified, created_at) 
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    if ($stmt->execute([$name, $email, $phone, $password, $verification_code])) {
        // **メール送信**
        if (sendVerificationEmail($email, $verification_code)) {
            // **セッションをセット**
            $_SESSION["pending_email"] = $email;
            session_write_close(); // セッションを明示的に保存

            // **認証ページへリダイレクト**
            header("Location: verify.php");
            exit();
        } else {
            echo "❌ 認証メールの送信に失敗しました。";
        }
    } else {
        echo "❌ アカウント作成に失敗しました。";
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新規登録</title>
    <link rel="stylesheet" href="css/register.css">
</head>
<body>
    <!-- ロゴ -->
    <div class="logo">
        <a href="index.php"><img src="image/logo.png" alt="FLUX"></a>
    </div>

    <div class="container">
        <!-- アカウント作成フォーム -->
        <div class="register-container">
            <h2>新規登録</h2>
            <form method="post">
                <input type="text" name="name" placeholder="ユーザー名" required><br>
                <input type="email" name="email" placeholder="メールアドレス" required><br>
                <input type="tel" name="phone" placeholder="電話番号" required><br>
                <input type="password" name="password" placeholder="パスワード" required><br>
                <button type="submit">登録</button>
            </form>
            <p>すでにアカウントをお持ちですか？ <a href="account.php">ログイン</a></p>
            <a class="top-button" href="index.php">TOPページへ戻る</a>
        </div>
    </div>
</body>
</html>
