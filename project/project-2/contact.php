<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars($_POST["name"], ENT_QUOTES, "UTF-8");
    $email = filter_var($_POST["email"], FILTER_SANITIZE_EMAIL);
    $subject = htmlspecialchars($_POST["subject"], ENT_QUOTES, "UTF-8");
    $message = htmlspecialchars($_POST["message"], ENT_QUOTES, "UTF-8");

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("無効なメールアドレスです。");
    }

    $to = "info@fluxpc.jp"; // ここにお問い合わせを受け取るメールアドレスを設定
    $headers = "From: " . $email . "\r\n" .
               "Reply-To: " . $email . "\r\n" .
               "Content-Type: text/plain; charset=UTF-8\r\n";

    $body = "お問い合わせがありました:\n\n";
    $body .= "お名前: $name\n";
    $body .= "メールアドレス: $email\n";
    $body .= "件名: $subject\n";
    $body .= "内容:\n$message\n";

    if (mail($to, "【お問い合わせ】$subject", $body, $headers)) {
        echo "<script>alert('お問い合わせが送信されました。'); window.location.href = 'index.php';</script>";
    } else {
        echo "<script>alert('送信に失敗しました。'); window.location.href = 'index.php';</script>";
    }
} else {
    echo "<script>alert('無効なリクエストです。'); window.location.href = 'index.php';</script>";
}
?>