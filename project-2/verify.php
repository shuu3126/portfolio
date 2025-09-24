<?php
require 'db.php';
session_save_path(__DIR__ . "/tmp");
session_start();

// 5分以上経過した未認証ユーザーを削除
$stmt = $pdo->prepare("
    DELETE FROM users 
    WHERE verified = 0 
    AND created_at < (NOW() - INTERVAL 5 MINUTE)
");
$stmt->execute();

// 🔥 削除後に該当する `pending_email` がまだ存在するかチェック
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND verified = 0");
$stmt->execute([$_SESSION["pending_email"] ?? null]);
$stillExists = $stmt->fetch();

if (!$stillExists) {
    unset($_SESSION["pending_email"]);
    unset($_SESSION["email"]);
    header("Location: register.php?error=expired");
    exit();
}

$error = "";
$success = "";

$email = $_SESSION["pending_email"];

// 認証コード再送信処理
if (isset($_POST["resend_code"])) {
    $current_time = time();
    $last_time = $_SESSION["last_verification_time"] ?? 0;

    if ($current_time - $last_time < 60) {
        $error = "再送信は1分に1回のみ可能です。";
    } else {
        $new_code = mt_rand(100000, 999999);
        $stmt = $pdo->prepare("UPDATE users SET verification_code = ? WHERE email = ?");
        if ($stmt->execute([$new_code, $email]) && sendVerificationEmail($email, $new_code)) {
            $_SESSION["last_verification_time"] = $current_time;
            $success = "新しい認証コードを送信しました。";
        } else {
            $error = "メールの送信に失敗しました。もう一度お試しください。";
        }
    }
}

// 認証コード確認処理
if (isset($_POST["verification_code"])) {
    $verification_code = trim($_POST["verification_code"]);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND verification_code = ?");
    $stmt->execute([$email, $verification_code]);
    $user = $stmt->fetch();

    if ($user) {
        $stmt = $pdo->prepare("UPDATE users SET verified = 1 WHERE email = ?");
        if ($stmt->execute([$email])) {
            unset($_SESSION["pending_email"]);
            unset($_SESSION["verify_attempts"]);
            unset($_SESSION["last_verification_time"]);
            header("Location: account.php");
            exit();
        } else {
            $error = "データベース更新に失敗しました。";
        }
    } else {
        $_SESSION["verify_attempts"] = ($_SESSION["verify_attempts"] ?? 0) + 1;
        if ($_SESSION["verify_attempts"] >= 5) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
            $stmt->execute([$email]);
            unset($_SESSION["pending_email"]);
            unset($_SESSION["verify_attempts"]);
            header("Location: register.php?error=too_many_attempts");
            exit();
        }
        $error = "認証コードが正しくありません。";
    }
}

// メール送信関数
function sendVerificationEmail($email, $code) {
    $subject = "【FLUX.PC】メール認証コード";
    $message = "以下の認証コードを入力してください。\n\n認証コード: {$code}";

    $headers = "From: FLUX.PC <info@fluxpc.jp>\r\n";
    $headers .= "Reply-To: info@fluxpc.jp\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return mail($email, $subject, $message, $headers);
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メール認証 - FLUX PC</title>
    <link rel="stylesheet" href="css/verify.css">
</head>
<body>
    <div class="verify">
        <h2>メール認証</h2>
        <p>ご登録のメールアドレス宛に、6桁の認証コードを送信しました。</p>
        <p>届いたコードを以下に入力してください。</p>

        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="text" name="verification_code" placeholder="認証コード (6桁)" required>
            <button type="submit" class="btn">認証する</button>
        </form>

        <form method="post" class="resend-form">
            <button type="submit" name="resend_code" class="btn secondary">認証コードを再送信</button>
        </form>

        <a href="register.php" class="back-link">▶ もう一度登録する</a>
    </div>
</body>
</html>
