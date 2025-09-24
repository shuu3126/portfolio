<?php
require_once __DIR__ . '/config.php'; // DB接続情報を取得

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("データベース接続に失敗しました: " . $e->getMessage());
}

// 商品リストを取得
$stmt = $pdo->query("SELECT id, name, price, image, description FROM products");
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>FLUX.PC - スマホ版</title>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700&display=swap" rel="stylesheet">
        <link rel="icon" type="image/png" href="image/logo.png">
        <link rel="stylesheet" href="css/sp.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                let slideIndex = 0;
                const slides = document.querySelectorAll(".slide");
                const slideWrapper = document.querySelector(".slide-wrapper");
                const totalSlides = slides.length;
                const cartButton = document.querySelector(".cart-button");
                if (cartButton) {
                    cartButton.addEventListener("click", function () {
                        window.location.href = "cart.php"; // カートページに移動
                    });
                }
                const buttons = document.querySelectorAll(".view-detail");
                buttons.forEach(button => {
                    button.addEventListener("click", function () {
                        const productId = this.getAttribute("data-id");
                        if (productId) {
                            window.location.href = `product.php?id=${productId}`;
                        }
                    });
                });
                function showSlides() {
                    slideIndex++;
                    if (slideIndex >= totalSlides) {
                        slideIndex = 0;
                    }
                    slideWrapper.style.transform = `translateX(-${slideIndex * 100}%)`;
                }
                
                setInterval(showSlides, 3000); // 3秒ごとにスライド
            });
        </script>
        <script src="https://unpkg.com/react@17/umd/react.development.js"></script>
        <script src="https://unpkg.com/react-dom@17/umd/react-dom.development.js"></script>
        <script src="https://unpkg.com/babel-standalone@6/babel.min.js"></script>
        <style>
            .slide-container {
                width: 100%;
                overflow: hidden;
                position: relative;
            }
            .slide-wrapper {
                display: flex;
                transition: transform 0.5s ease-in-out;
                width: 100%; /* 画像が1枚ずつスライドするように調整 */
            }
            .slide {
                width: 100%; /* 各スライドの幅を100%に設定 */
                flex: 0 0 100%; /* 1枚ずつスライド */
                max-width: 400px; /* 最大幅を設定 */
                margin: 0 auto; /* 中央配置 */
            }
        </style>
    </head>
    <body>
        <!-- ヘッダー -->
        <header class="header">
            <div class="header-container">
                <!-- ✅ 左側のロゴ -->
                <a href="index.php" class="logo">
                    <img src="image/had.png" alt="FLUX PC 公式オンラインショップ">
                </a>

                <!-- ✅ 右上にカートボタンを配置 -->
                <button class="cart-button">
                    <i class="fas fa-shopping-cart"></i>
                </button>
            </div>
            <div class="user-menu">
                <a href="account.php"><i class="fas fa-user-circle" id="user-icon"></i></a>
            </div>
        </header>

        <div class="slide-container">
            <div class="slide-wrapper">
                <img class="slide" src="image/image1.jpg" alt="1">
                <img class="slide" src="image/image2.jpg" alt="2">
                <img class="slide" src="image/image3.jpg" alt="3">
            </div>
        </div>
        <div class="after-slide">
            <!-- ✅ タイトル（上部） -->
            <h2>パソコンのプロが組み上げます</h2>

            <!-- ✅ 画像とテキストを横並びに配置 -->
            <div class="after-slide-content">
                <!-- ✅ 画像（左） -->
                <img src="image/bana-.jpg" alt="PC組み立て">

                <!-- ✅ 箇条書きテキスト（右） -->
                <div class="banner-text">
                    <ul>
                        <li>最新のパーツを使用した高性能PC</li>
                        <li>カスタムオプションで自由に選択</li>
                        <li>カウンセリングからオリジナルパソコンを</li>
                        <li>アフターサポート付き</li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- 商品一覧 -->
        <section class="product-list">
            <h2>オーダーパソコン</h2>
            <div class="products">
                <div class="product">
                    <img src="image/imagek1.png" alt="商品1">
                    <h3>オリジナルパソコン</h3>
                    <p>要相談</p>
                    <button onclick="window.location.href='https://lin.ee/kj9k6NF'">問合せ</button>
                </div>
                <div class="product">
                    <img src="image/imagek2.png" alt="商品2">
                    <h3>ミドルスペック</h3>
                    <p>¥80,000~</p>
                    <button onclick="window.location.href='https://lin.ee/kj9k6NF'">問合せ</button>
                </div>
                <div class="product">
                    <img src="image/imagek3.png" alt="商品3">
                    <h3>ハイスペック</h3>
                    <p>¥200,000~</p>
                    <button onclick="window.location.href='https://lin.ee/kj9k6NF'">問合せ</button>
                </div>
            </div>
        </section>
        <section class="product-list2">
            <h2>キャンペーンモデル</h2>
            <div class="products2">
                <div class="product2">
                    <img src="image/seel1.png" alt="商品7">
                    <h3>コスパ重視モデル</h3>
                    <p class="product-note">新生活応援キャンペーン対象商品</p>
                    <p>販売価格 ¥86,000</p>
                    <button class="view-detail" onclick="location.href='product.php?id=7'">商品詳細</button>
                </div>
            </div>
        </section>
        <section class="product-list2">
            <h2>FLUX シリーズ</h2>
            <div class="products2">
                <div class="product2">
                    <img src="image/I5.png" alt="商品1">
                    <h3>FLUX I5</h3>
                    <p class="product-note">intel corei5+RTX4060搭載モデル</p>
                    <p>販売価格 ¥119,000</p>
                    <button class="view-detail" onclick="location.href='product.php?id=1'">商品詳細</button>
                </div>
                <div class="product2">
                    <img src="image/I7.png" alt="商品2">
                    <h3>FLUX I7</h3>
                    <p class="product-note">intel corei7+RTX4060ti搭載モデル</p>
                    <p>販売価格 ¥172,900</p>
                    <button class="view-detail" onclick="location.href='product.php?id=2'">商品詳細</button>
                </div>
                <div class="product2">
                    <img src="image/I9.png" alt="商品3">
                    <h3>FLUX I9</h3>
                    <p class="product-note">intel corei9+RTX4070ti搭載モデル</p>
                    <p>販売価格 ¥259,000</p>
                    <button class="view-detail" onclick="location.href='product.php?id=3'">商品詳細</button>
                </div>
                <div class="product2">
                    <img src="image/R5.png" alt="商品4">
                    <h3>FLUX R5</h3>
                    <p class="product-note">Ryzen5+RX7700搭載モデル</p>
                    <p>販売価格 ¥94,900</p>
                    <button class="view-detail" onclick="location.href='product.php?id=4'">商品詳細</button>
                </div>
                <div class="product2">
                    <img src="image/R7.png" alt="商品5">
                    <h3>FLUX R7</h3>
                    <p class="product-note">Ryzen7+RX7800搭載モデル</p>
                    <p>販売価格 ¥164,900</p>
                    <button class="view-detail" onclick="location.href='product.php?id=5'">商品詳細</button>
                </div>
                <div class="product2">
                    <img src="image/R9.png" alt="商品6">
                    <h3>FLUX R9</h3>
                    <p class="product-note">Ryzen9+RX7800xt搭載モデル</p>
                    <p>販売価格 ¥229,000</p>
                    <button class="view-detail" onclick="location.href='product.php?id=6'">商品詳細</button>
                </div>
            </div>
        </section>
        <div id="floating-button-root"></div>

        <!-- フッター -->
        <footer>
            <p>&copy; FLUX.PC. All Rights Reserved.</p>
            <div class="footer-links">
                <a href="contact.html">お問い合わせ</a>
                <a href="privacy-policy.html">プライバシーポリシー</a>
                <a href="terms.html">利用規約</a>
                <a href="tokusyouhou.html">特定商取引法に基づく表記</a>
            </div>
        </footer>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const buttons = document.querySelectorAll(".view-detail");

            buttons.forEach(button => {
                button.addEventListener("click", function () {
                    const productId = this.getAttribute("data-id");
                    if (productId) {
                        // `product-detail.html` にIDをクエリパラメータとして渡す
                        window.location.href = `product.php?id=${productId}`;
                    }
                });
            });
        });
    </script>
    </body>
    <script type="text/babel">
      function FloatingButton({ onClick }) {
        return (
          <button
            onClick={onClick}
            style={{
              position: "fixed",
              bottom: "20px",
              right: "20px",
              backgroundColor: "transparent",
              border: "none",
              cursor: "pointer",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              boxShadow: "0px 4px 6px rgba(0, 0, 0, 0.1)"
            }}
          >
            <img src="image/custom-icon.png" alt="お問い合わせ" style={{ width: "60px", height: "60px" }} />
          </button>
        );
      }

      function MainComponent() {
        const handleClick = () => {
          window.location.href = "https://lin.ee/1WQrh6q";
        };

        return (
          <div className="min-h-screen">
            <FloatingButton onClick={handleClick} />
          </div>
        );
      }

      ReactDOM.render(<MainComponent />, document.getElementById("floating-button-root"));
    </script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var userIcon = document.getElementById("user-icon");
        var dropdown = document.getElementById("user-dropdown");

        userIcon.addEventListener("click", function() {
            dropdown.style.display = (dropdown.style.display === "block") ? "none" : "block";
        });

        document.addEventListener("click", function(event) {
            if (!userIcon.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.style.display = "none";
            }
        });
    });
    </script>
</html>