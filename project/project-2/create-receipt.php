<?php
require_once __DIR__ . '/../vendor/autoload.php'; // Composerのオートロード
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
require 'db.php';

use Dotenv\Dotenv;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// `.env` をロード
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

session_start();
$stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? '';
if (empty($stripeSecretKey)) {
    file_put_contents(__DIR__ . "/webhook.log", "❌ STRIPE_SECRET_KEY が取得できません\n", FILE_APPEND);
    exit("Stripe API key is missing.");
}
Stripe::setApiKey($stripeSecretKey);

// **Webhookの署名検証**
$endpoint_secret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? null;

file_put_contents(__DIR__ . "/webhook.log", date("Y-m-d H:i:s") . " - Webhook received\n", FILE_APPEND);

if (!$sig_header) {
    file_put_contents(__DIR__ . "/webhook.log", "⚠️ Stripe Signature not found\n", FILE_APPEND);
    exit;
}

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    file_put_contents(__DIR__ . "/webhook.log", "✅ Webhook event received: " . json_encode($event) . "\n", FILE_APPEND);

    if ($event->type == 'checkout.session.completed') {
        $session = $event->data->object;
        $session_id = $session->id;
        $customer_name = $session->customer_details->name ?? '不明';
        $customer_phone = $session->customer_details->phone ?? '不明';
        $customer_address = $session->customer_details->address->line1 ?? '不明';
        $customer_email = $session->customer_details->email ?? '不明';
        $order_date = date("Y-m-d");
        $expected_delivery = date("Y-m-d", strtotime("+17 days")); // 2週間半後
        $order_id = strtoupper(uniqid("ORDER-"));
        $_SESSION['transaction_id'] = $order_id;
        $transaction_id = $order_id; // 変数名も統一して以降で使う

        if (!$user_id) {
            // もしゲストが後からアカウントを作る場合、メールアドレスと `users` テーブルを紐付けられるようにする
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$customer_email]);
            $user = $stmt->fetch();

            if ($user) {
                $user_id = $user["id"];
                // `purchases` の `user_id` を更新
                $updateStmt = $pdo->prepare("UPDATE purchases SET user_id = ? WHERE transaction_id = ?");
                $updateStmt->execute([$user_id, $session_id]);
            }
        }

        // **Stripe API で `line_items` を取得**
        try {
            $line_items = \Stripe\Checkout\Session::allLineItems($session_id, ['limit' => 10]);
        } catch (Exception $e) {
            file_put_contents(__DIR__ . "/webhook.log", "❌ Error fetching line items: " . $e->getMessage() . "\n", FILE_APPEND);
            exit("Error fetching line items: " . $e->getMessage());
        }

        // **日本円の金額計算を修正**
        $products = [];
        $total = 0;
        $currency = strtoupper($session->currency ?? 'JPY'); // 通貨を取得

        if (!empty($line_items->data)) {
            foreach ($line_items->data as $item) {
                $unit_price = ($item->price->unit_amount ?? 0) / 1;
                $subtotal = $unit_price * $item->quantity;
                $total += $subtotal;

                // **日本円の場合、¥表記で統一**
                if ($currency === 'JPY') {
                    $unit_price = number_format($unit_price, 0, '.', ','); 
                    $subtotal = number_format($subtotal, 0, '.', ',');
                }

                $products[] = [
                    'name' => $item->description ?? '商品名不明',
                    'quantity' => $item->quantity ?? 1,
                    'unit_price' => "¥" . $unit_price,
                    'subtotal' => "¥" . $subtotal,
                ];
            }
        } else {
            file_put_contents(__DIR__ . "/webhook.log", "⚠️ No items found in the order.\n", FILE_APPEND);
        }

        // **消費税と総額計算**
        $grand_total = $total;

        // **支払い情報を取得**
        $payment_method = $session->payment_method_types[0] ?? '不明';
        $payment_details = match ($payment_method) {
            'card' => "クレジットカード",
            'paypal' => "PayPal",
            'konbini' => "コンビニ支払い",
            default => "その他（" . ucfirst($payment_method) . "）"
        };

        // **領収書を作成**
        $receipt_dir = __DIR__ . "/../public_html/receipts/";
        if (!is_dir($receipt_dir)) {
            mkdir($receipt_dir, 0775, true);
        }
        $receipt_file = $receipt_dir . $order_id . ".pdf";

        // **TCPDF を設定**
        $pdf = new TCPDF();
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('FLUX PC');
        $pdf->SetTitle('領収書');
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();

        // **フォント設定（日本語）**
        $pdf->SetFont('cid0jp', '', 12);

        // **ヘッダー**
        $pdf->SetFont('cid0jp', 'B', 16);
        $pdf->Cell(0, 10, "領収書", 0, 1, 'C');
        $pdf->Ln(5);

        // **会社情報**
        $pdf->SetFont('cid0jp', '', 12);
        $pdf->Cell(0, 8, "発行元 : FLUX PC", 0, 1, 'L');
        $pdf->Cell(0, 8, "担当   : 貞方", 0, 1, 'L');
        $pdf->Cell(0, 8, "発行日 : " . date("Y-m-d"), 0, 1, 'L');
        $pdf->Ln(5);

        // **注文情報**
        $pdf->Cell(0, 8, "注文番号      : " . $order_id, 0, 1, 'L');
        $pdf->Cell(0, 8, "決済完了日    : " . $order_date, 0, 1, 'L');
        $pdf->Cell(0, 8, "お客様氏名    : " . $customer_name, 0, 1, 'L');
        $pdf->Cell(0, 8, "電話番号      : " . $customer_phone, 0, 1, 'L');
        $pdf->Cell(0, 8, "住所          : " . $customer_address, 0, 1, 'L');
        $pdf->Cell(0, 8, "お客様 Email  : " . $customer_email, 0, 1, 'L');
        $pdf->Cell(0, 8, "決済方法      : " . $payment_details, 0, 1, 'L');
        $pdf->Cell(0, 8, "予想お届け日  : " . $expected_delivery, 0, 1, 'L');
        $pdf->Ln(5);

        // **購入商品リスト**
        $pdf->Cell(60, 8, "商品名", 1, 0, 'C');
        $pdf->Cell(30, 8, "数量", 1, 0, 'C');
        $pdf->Cell(30, 8, "単価", 1, 0, 'C');
        $pdf->Cell(40, 8, "小計", 1, 1, 'C');

        foreach ($products as $product) {
            $pdf->Cell(60, 8, $product['name'], 1, 0, 'L');
            $pdf->Cell(30, 8, $product['quantity'], 1, 0, 'C');
            $pdf->Cell(30, 8, $product['unit_price'], 1, 0, 'R');
            $pdf->Cell(40, 8, $product['subtotal'], 1, 1, 'R');
        }

        // **合計金額の表示**
        $pdf->Ln(5); // 少し余白を作る
        $pdf->SetFont('cid0jp', 'B', 12); // 合計金額は太字に
        $pdf->Cell(120, 8, "合計金額", 1, 0, 'R');
        $pdf->Cell(40, 8, "¥" . number_format($total, 0, '.', ','), 1, 1, 'R'); // 合計金額を右寄せ

        // **PDF の保存**
        $pdf->Output($receipt_file, 'F');

        file_put_contents(__DIR__ . "/webhook.log", "✅ 領収書保存: " . $receipt_file . "\n", FILE_APPEND);

        // --- 注文情報をSQLに保存 ---
        $purchased_items = array_map(fn($p) => "{$p['name']} x {$p['quantity']}", $products);
        $stmt = $pdo->prepare("INSERT INTO purchases (user_id, transaction_id, total_price, items, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([
            $user_id ?? null,          // ログインしてなければNULL
            $transaction_id,           // ← order_id と同じ
            $grand_total,
            json_encode($purchased_items)
        ]);
        file_put_contents(__DIR__ . "/webhook.log", "✅ DBに購入情報を保存: $transaction_id\n", FILE_APPEND);


        // **ここからメール送信処理を開始**
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $_ENV['SMTP_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['SMTP_USER'];
            $mail->Password = $_ENV['SMTP_PASS'];
            $mail->SMTPSecure = 'ssl'; 
            $mail->Port = 465;

            // **エンコーディング設定**
            $mail->CharSet = 'UTF-8'; 
            $mail->Encoding = 'base64';

            // **運営側へのメール送信**
            $mail->setFrom($_ENV['SMTP_USER'], 'FLUX PC');
            $mail->addAddress($_ENV['ADMIN_EMAIL'], 'FLUX 管理者');
            $mail->Subject = "【FLUX PC】新しい注文";
            $mail->Body = "新しい注文が入りました。\n\n"
                         . "注文番号: $order_id\n"
                         . "決済完了日: $order_date\n"
                         . "お客様氏名: $customer_name\n"
                         . "電話番号: $customer_phone\n"
                         . "お客様 Email: $customer_email\n"
                         . "合計金額: ¥" . number_format($grand_total, 0, '.', ',') . "\n\n"
                         . "決済方法: $payment_details\n"
                         . "予想お届け日: $expected_delivery\n\n"
                         . "【購入商品リスト】\n";
            foreach ($products as $product) {
                $mail->Body .= "{$product['name']} x {$product['quantity']} - {$product['subtotal']}\n";
            }
            if (file_exists($receipt_file)) {
                $mail->addAttachment($receipt_file);
            }

            if (!$mail->send()) {
                file_put_contents(__DIR__ . "/webhook.log", "❌ 運営メール送信エラー: " . $mail->ErrorInfo . "\n", FILE_APPEND);
            } else {
                file_put_contents(__DIR__ . "/webhook.log", "✅ 運営メール送信成功！\n", FILE_APPEND);
            }

            // **ユーザー側へのメール送信**

            $mail = new PHPMailer(true); // **新しいインスタンスを作成**
            $mail->isSMTP();
            $mail->Host = $_ENV['SMTP_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['SMTP_USER'];
            $mail->Password = $_ENV['SMTP_PASS'];
            $mail->SMTPSecure = 'ssl'; // TLSなら 'tls'
            $mail->Port = 465; // TLSなら587

            // **ヘッダーの最適化**
            $mail->CharSet = 'UTF-8'; 
            $mail->Encoding = 'quoted-printable'; // **変更：base64 → quoted-printable**
            $mail->isHTML(false); // **プレーンテキストのみにする**
            $mail->XMailer = "FLUX Mailer"; // **なりすまし判定を回避**
            $mail->MessageID = "<" . uniqid() . "@fluxpc.jp>"; // **一意のMessage-IDを設定**
            $mail->Priority = 3; // **通常の優先度に設定**

            $mail->setFrom($_ENV['SMTP_USER'], 'FLUX PC'); 
            $mail->Sender = $_ENV['SMTP_USER']; // **Return-Pathを一致**
            $mail->ReturnPath = $_ENV['SMTP_USER']; // **Return-Pathの明示**

            $mail->addAddress($customer_email); // **ユーザーのメールアドレスを宛先に追加**
            $mail->addReplyTo($_ENV['SMTP_USER']); // **返信先を運営に固定**

            $mail->Subject = "【FLUX PC】ご購入ありがとうございます";
            $mail->Body = "このたびはご購入いただきありがとうございます。\n\n"
                        . "注文番号: $order_id\n"
                        . "決済完了日: $order_date\n"
                        . "お客様氏名: $customer_name\n"
                        . "電話番号: $customer_phone\n"
                        . "住所: $customer_address\n"
                        . "予想お届け日: $expected_delivery\n"
                        . "合計金額: ¥" . number_format($grand_total) . "\n\n"
                        . "詳細は領収書をご確認ください。\n"
                        . "何かご不明な点がございましたら、support@fluxpc.jp までご連絡ください。\n\n"
                        . "--\nFLUX PC サポート";

            if (!$mail->send()) {
                file_put_contents(__DIR__ . "/webhook.log", "❌ メール送信エラー: " . $mail->ErrorInfo . "\n", FILE_APPEND);
            } else {
                file_put_contents(__DIR__ . "/webhook.log", "✅ メール送信成功: " . $customer_email . "\n", FILE_APPEND);
            }
        } catch (Exception $e) {
            file_put_contents(__DIR__ . "/webhook.log", "❌ PHPMailerエラー: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
} catch (Exception $e) {
    exit("Webhook Error: " . $e->getMessage());
}
