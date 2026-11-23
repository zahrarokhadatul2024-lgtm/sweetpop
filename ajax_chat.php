<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Disable error display in JSON output
mysqli_report(MYSQLI_REPORT_OFF);

$inputData = json_decode(file_get_contents('php://input'), true);

// Support reset memory action
if (isset($inputData['action']) && $inputData['action'] === 'reset') {
    echo json_encode([
        'status' => 'success',
        'message' => 'Chat memory reset successfully.'
    ]);
    exit;
}

$message = trim($inputData['message'] ?? '');
$sessionId = trim($inputData['sessionId'] ?? session_id() ?: uniqid('sweet_', true));

if ($message === '') {
    echo json_encode([
        'status' => 'error',
        'reply' => 'Pesan tidak boleh kosong ya Kak! 🍰'
    ]);
    exit;
}

// Retrieve recent chat history for multi-turn conversation context (up to last 4 messages / 2 pairs)
$chatHistory = [];
if (!empty($sessionId)) {
    $hStmt = mysqli_prepare($con, "
        SELECT user_message, bot_response 
        FROM ai_chat_logs 
        WHERE session_id = ? AND is_out_of_scope = 0 
        ORDER BY id DESC LIMIT 4
    ");
    if ($hStmt) {
        mysqli_stmt_bind_param($hStmt, "s", $sessionId);
        mysqli_stmt_execute($hStmt);
        $hRes = mysqli_stmt_get_result($hStmt);
        $rawRows = [];
        while ($row = mysqli_fetch_assoc($hRes)) {
            $rawRows[] = $row;
        }
        mysqli_stmt_close($hStmt);
        $chatHistory = array_reverse($rawRows);
    }
}
$hasPriorHistory = !empty($chatHistory);

$lowerMsg = mb_strtolower($message, 'UTF-8');

// ========================================================
// 1. OUT-OF-SCOPE GUARDRAILS & TOPIC CLASSIFICATION
// ========================================================

// Off-topic forbidden patterns (Politics, coding, general science, sports, tech gadgets, automotive, gaming)
$outOfScopePatterns = [
    '/\b(presiden|menteri|partai|pemilu|pilpres|politik|pemerintah|dpr|parlemen)\b/i',
    '/\b(perang|senjata|militer|russia|ukraina|israel|palestina|invasi)\b/i',
    '/\b(python|javascript|koding|coding|source code|java\b|c\+\+|html\b.*kode|sql injection|compiler|debugging|github)\b/i',
    '/\b(kalkulus|integral|turunan\b.*matematika|rumus fisika|relativitas|kecepatan cahaya|gravitasi bumi)\b/i',
    '/\b(ibu kota\b|negara terluas|sungai terpanjang|gunung tertinggi|sejarah peradaban|perang dunia)\b/i',
    '/\b(sepak bola|piala dunia|liga inggris|liga champions|real madrid|barcelona|ronaldo|messi)\b/i',
    '/\b(buatkan.*puisi|buatkan.*cerpen|buatkan.*surat lamaran|buatkan.*skripsi|buatkan.*makalah)\b/i',
    '/\b(saham|crypto|bitcoin|forex|investasi bodong|judi|slot gacor)\b/i',
    '/\b(anime|naruto|one piece|manga|film hollywood|aktor|aktris)\b/i',
    '/\b(komputer|laptop|pc gaming|gadget|rakit\b.*komputer|handphone|smartphone|android|ios|motor\b|mobil\b|otomotif|mesin motor|gaming)\b/i'
];

$isStrictlyOutOfScope = false;
foreach ($outOfScopePatterns as $pat) {
    if (preg_match($pat, $lowerMsg)) {
        $isStrictlyOutOfScope = true;
        break;
    }
}

// In-scope domain keywords for SweetPop (using word boundary check)
$sweetpopDomainKeywords = [
    // Bakery & Dessert
    'kue', 'cake', 'dessert', 'bakery', 'brownies', 'cookies', 'biskuit', 'pudding', 'cupcake',
    'tart', 'roti', 'pastry', 'mille crepe', 'crepe', 'topping', 'sponge', 'whipped cream', 'cream',
    'vanila', 'cokelat', 'chocolate', 'matcha', 'strawberry', 'stroberi', 'red velvet', 'biscoff',
    'lotus', 'oreo', 'tiramisu', 'chiffon', 'makanan', 'cemilan', 'snack', 'minuman', 'drink',
    'porsi', 'manis', 'lezat', 'enak', 'treats',
    // SweetPop Store & Location
    'sweetpop', 'sweetie', 'toko', 'bakehouse', 'dapur', 'alamat', 'lokasi', 'jogja', 'yogyakarta',
    'sleman', 'kaliurang', 'buka', 'tutup', 'operasional', 'kontak', 'hubungi', 'whatsapp',
    'nomor wa', 'no wa', 'admin', 'cs', 'zahra',
    // Orders & Transactions
    'pesan', 'order', 'beli', 'checkout', 'keranjang', 'cart', 'bayar', 'pembayaran',
    'qris', 'transfer', 'bca', 'cod', 'tunai', 'gopay', 'ovo', 'shopeepay', 'harga', 'biaya',
    'katalog', 'menu', 'stok', 'ready',
    // Delivery & Shipping
    'kirim', 'ongkir', 'antar', 'delivery', 'pengantaran', 'gratis ongkir', 'free delivery',
    'kurir', 'slot', 'jadwal', 'waktu', 'pagi', 'siang', 'sore', 'malam', 'hari ini', 'besok',
    'resi', 'lacak', 'tracking', 'invoice', 'spx',
    // Occasions & Customization
    'ulang tahun', 'ultah', 'birthday', 'anniversary', 'hampers', 'hadiah', 'gift', 'kado',
    'wisuda', 'event', 'pesta', 'ucapan', 'tulisan', 'lilin', 'topper', 'custom', 'custom cake',
    // Ingredients, Quality & Storage
    'halal', 'bahan', 'komposisi', 'resep', 'chiller', 'kulkas', 'freezer', 'simpan', 'tahan',
    'basi', 'expired', 'kedaluwarsa', 'suhu', 'dingin', 'higienis', 'alergi', 'alergen',
    'gluten', 'susu', 'dairy', 'telur', 'kacang',
    // Promos & Accounts
    'diskon', 'promo', 'voucher', 'kupon', 'sweet20', 'potongan', 'poin', 'member', 'vip',
    'akun', 'login', 'daftar', 'register',
    // Friendly greetings & AI identity
    'halo', 'hai', 'hello', 'hi', 'assalamualaikum',
    'terima kasih', 'makasih', 'thanks', 'siapa kamu', 'asisten', 'bot', 'bantuan', 'help', 'tanya'
];

$domainMatchCount = 0;
foreach ($sweetpopDomainKeywords as $dk) {
    if (preg_match('/\b' . preg_quote($dk, '/') . '\b/iu', $lowerMsg)) {
        $domainMatchCount++;
    }
}

// Strict Out-of-Scope check:
// 1. Strictly matches forbidden regex pattern (politics, coding, sports, science, gaming, etc.)
// 2. OR for brand-new conversation without prior history, message length > 12 and 0 domain keywords match
// 3. OR for ongoing conversation with history, message length > 45 and 0 domain keywords match
$shouldBlockOos = false;
if ($isStrictlyOutOfScope) {
    $shouldBlockOos = true;
} elseif (!$hasPriorHistory && mb_strlen($lowerMsg) > 12 && $domainMatchCount === 0) {
    $shouldBlockOos = true;
} elseif ($hasPriorHistory && mb_strlen($lowerMsg) > 45 && $domainMatchCount === 0) {
    $shouldBlockOos = true;
}

if ($shouldBlockOos) {
    $outOfScopeReply = "Maaf ya Kak! 🍰 Sebagai asisten virtual resmi **SweetPop**, aku khusus diprogram untuk membantu seputar **menu dessert, pemesanan kue, jadwal pengantaran, rekomendasi acara manis, dan kebijakan toko SweetPop**.\n\nAku tidak dapat menjawab pertanyaan di luar topik toko kue kami. Ada yang bisa Sweetie bantu seputar kue lezat atau pesanan Kakak di SweetPop hari ini? 🎀✨";
    
    // Log to DB
    $logStmt = mysqli_prepare($con, "INSERT INTO ai_chat_logs (session_id, user_id, user_message, bot_response, intent, is_out_of_scope) VALUES (?, ?, ?, ?, 'out_of_scope', 1)");
    $uid = current_user_id() ?: null;
    mysqli_stmt_bind_param($logStmt, "siss", $sessionId, $uid, $message, $outOfScopeReply);
    mysqli_stmt_execute($logStmt);

    echo json_encode([
        'status' => 'success',
        'reply' => $outOfScopeReply,
        'is_out_of_scope' => true,
        'is_gemini' => false,
        'suggestions' => [
            '🍰 Menu Best Seller',
            '🚚 Jadwal & Ongkir',
            '❄️ Tips Simpan Kue',
            '🎂 Custom Ucapan & Lilin'
        ]
    ]);
    exit;
}

// ========================================================
// 2. LIVE ORDER TRACKING (If message contains SPX- or #SP-)
// ========================================================
if (preg_match('/(spx-[\w-]+|\#?sp-\d+|\border\s*#?\d+)/i', $message, $trkMatches)) {
    $rawTrk = trim($trkMatches[0]);
    $cleanNum = preg_replace('/[^\d]/', '', $rawTrk);

    $orderRow = null;
    if (!empty($cleanNum)) {
        $ordStmt = mysqli_prepare($con, "SELECT * FROM orders WHERE order_id = ? OR trx_id LIKE ? LIMIT 1");
        $likeTrk = "%" . $rawTrk . "%";
        mysqli_stmt_bind_param($ordStmt, "is", $cleanNum, $likeTrk);
        mysqli_stmt_execute($ordStmt);
        $orderRow = mysqli_fetch_assoc(mysqli_stmt_get_result($ordStmt));
    }

    if ($orderRow) {
        $statusLabels = [
            'pending' => '⏳ Menunggu Konfirmasi / Pembayaran',
            'processing' => '👩‍🍳 Sedang Dipanggang & Disiapkan di Dapur',
            'completed' => '🚚 Selesai / Sedang Diantar Kurir ke Alamat',
            'cancelled' => '❌ Pesanan Dibatalkan'
        ];
        $stText = $statusLabels[$orderRow['status']] ?? ucfirst($orderRow['status']);
        
        $reply = "🔍 **Hasil Pelacakan Pesanan:**\n\n"
               . "• **No. Pesanan:** #SP-" . str_pad($orderRow['order_id'], 4, '0', STR_PAD_LEFT) . "\n"
               . "• **Nama Penerima:** " . htmlspecialchars($orderRow['customer_name']) . "\n"
               . "• **Status Terkini:** **" . $stText . "**\n"
               . "• **Jadwal Antar:** " . (!empty($orderRow['delivery_date']) ? date('d M Y', strtotime($orderRow['delivery_date'])) : 'Hari Ini') . " (" . htmlspecialchars($orderRow['delivery_time'] ?: 'Slot Standar') . ")\n"
               . "• **Total Tagihan:** Rp " . number_format($orderRow['total_amount'], 0, ',', '.') . " (" . htmlspecialchars($orderRow['payment_method']) . ")\n\n"
               . "Kakak juga bisa melihat atau mencetak faktur resmi di halaman [Invoice Digital](invoice.php?order_id=" . $orderRow['order_id'] . ") ya! 🍰✨";

        // Log
        $logStmt = mysqli_prepare($con, "INSERT INTO ai_chat_logs (session_id, user_id, user_message, bot_response, intent, is_out_of_scope) VALUES (?, ?, ?, ?, 'order_tracking', 0)");
        $uid = current_user_id() ?: null;
        mysqli_stmt_bind_param($logStmt, "siss", $sessionId, $uid, $message, $reply);
        mysqli_stmt_execute($logStmt);

        echo json_encode([
            'status' => 'success',
            'reply' => $reply,
            'is_out_of_scope' => false,
            'is_gemini' => false,
            'suggestions' => ['🍰 Lihat Menu Lain', '🚚 Info Jam Pengantaran', 'Hubungi WhatsApp']
        ]);
        exit;
    }
}

// Fetch active KMS items for both Gemini Context and Local Matcher
$kmsQuery = "SELECT * FROM kms_knowledge WHERE is_active = 1 ORDER BY id ASC";
$kmsRes = mysqli_query($con, $kmsQuery);
$kmsItems = [];
$kmsTextSummary = "";
if ($kmsRes) {
    while ($k = mysqli_fetch_assoc($kmsRes)) {
        $kmsItems[] = $k;
        $kmsTextSummary .= "• Topik: {$k['title']} (Kategori: {$k['category']})\n  Konten: " . str_replace("\n", " ", $k['content']) . "\n";
    }
}

// Fetch active products for Context
$prodRes = mysqli_query($con, "SELECT p.*, c.cat_title FROM products p JOIN categories c ON p.product_cat = c.cat_id WHERE p.product_status = 'active'");
$allActiveProducts = [];
$productsTextSummary = "";
if ($prodRes) {
    while ($p = mysqli_fetch_assoc($prodRes)) {
        $allActiveProducts[] = $p;
        $productsTextSummary .= "- {$p['product_title']} (Kategori: {$p['cat_title']}, Harga: Rp " . number_format($p['product_price'], 0, ',', '.') . ", Stok: {$p['product_stock']}, Deskripsi: {$p['product_description']})\n";
    }
}

// ========================================================
// 3. CALL REAL GOOGLE GEMINI 1.5 / 2.5 / 3.5 FLASH API
// ========================================================
$geminiApiKey = get_gemini_api_key();
$geminiReply = null;

if (!empty($geminiApiKey)) {
    $primaryModel = get_gemini_model() ?: 'gemini-3.5-flash';
    $modelsToTry = array_unique([$primaryModel, 'gemini-3.5-flash', 'gemini-3.1-flash-lite', 'gemini-3-flash-preview', 'gemini-flash-latest', 'gemini-2.5-flash']);

    $systemInstruction = "Kamu adalah Sweetie AI, asisten virtual resmi toko kue & bakery 'SweetPop' di Yogyakarta (Aesthetic Dessert Store).\n"
        . "Karaktermu: Sangat ramah, bersahabat, hangat, ceria, menggunakan emoji manis (🍰, 🍓, 🧁, 🎀, ✨), dan berbahasa Indonesia natural.\n\n"
        . "ATURAN UTAMA (GUARDRAILS KETAT):\n"
        . "1. Kamu HANYA boleh menjawab pertanyaan seputar SweetPop: menu kue/dessert, harga, rekomendasi acara (ulang tahun, hampers, anniversary), slot jadwal pengantaran (Pagi 09-12, Siang 12-15, Sore 15-18, Malam 18-21), lilin perayaan gratis, pesan tulisan kue (cake message maks 60 huruf), cara pesan, voucher SWEET20, gratis ongkir min. 50k, cara simpan di kulkas chiller (tahan 3-4 hari) / freezer (14 hari), status kehalalan (100% halal tanpa rum), jam buka (09:00 - 21:00 WIB), alamat (Jl. Kaliurang KM 5 Sleman Yogyakarta).\n"
        . "2. JIKA pengguna bertanya tentang topik di luar SweetPop (seperti politik, presiden, koding/pemrograman komputer, rumus fisika/matematika, gadget, otomotif/motor/mobil, gosip/film/anime, atau tugas sekolah umum):\n"
        . "   KAMU WAJIB MENOLAK DENGAN RAMAH DAN MANIS. Contoh penolakan: 'Maaf ya Kak! 🍰 Sebagai asisten virtual resmi SweetPop, aku khusus diprogram untuk membantu seputar menu dessert, pemesanan kue, jadwal pengantaran, dan kebijakan toko SweetPop. Ada yang bisa Sweetie bantu seputar kue lezat atau pesanan Kakak di SweetPop hari ini? 🎀✨'\n"
        . "3. Gunakan data resmi toko SweetPop berikut sebagai kebenaran mutlak:\n"
        . "--- INFORMASI LENGKAP TOKO & MENU SWEETPOP ---\n" . $kmsTextSummary . "\n"
        . "--- DAFTAR MENU DESSERT AKTIF ---\n" . $productsTextSummary . "\n"
        . "4. PENTING: Jangan pernah menyebut istilah 'Knowledge Management System', 'KMS', ataupun 'database' dalam jawabanmu kepada pembeli. Jawablah secara alami, ramah, dan bersahabat seperti staf toko kue SweetPop.\n"
        . "5. Format jawaban dengan rapi menggunakan poin-poin jika perlu, dan sertakan tautan belanja ke [store.php](store.php) jika merekomendasikan menu.\n"
        . "6. JIKA PENGGUNA MENANYAKAN RESEP / CARA BUAT DESSERT: Jelaskan dengan ramah bahwa resep kue SweetPop adalah resep rahasia chef bakehouse SweetPop, namun sebutkan bahwa semua produk SweetPop dibuat dari bahan premium 100% Halal tanpa rum, mentega berkualitas, susu segar, dan cokelat murni tanpa bahan pengawet. Kamu boleh membagikan tips atau resep rumahan simpel ala SweetPop, atau mengajak pelanggan memesan langsung menu siap santap di SweetPop dengan melihat katalog store.php!\n"
        . "7. MEMORI PERCAKAPAN (MULTI-TURN CHAT): Kamu memiliki ingatan riwayat obrolan di atas. Jika pelanggan mengajukan pertanyaan lanjutan (misalnya 'kalau yang nomor satu?', 'berapa harganya?', 'yang itu tahan berapa hari?', 'bisa dipotong jadi berapa?'), pahami konteks menu kue atau topik yang sedang dibahas sebelumnya secara cerdas, ramah, dan tepat tanpa bertanya balik kecuali perlu konfirmasi.";

    // Build multi-turn conversational contents
    $geminiContents = [];
    foreach ($chatHistory as $hTurn) {
        $uMsg = trim($hTurn['user_message'] ?? '');
        $bMsg = trim($hTurn['bot_response'] ?? '');
        if ($uMsg !== '' && $bMsg !== '') {
            $geminiContents[] = [
                'role' => 'user',
                'parts' => [
                    ['text' => $uMsg]
                ]
            ];
            $geminiContents[] = [
                'role' => 'model',
                'parts' => [
                    ['text' => $bMsg]
                ]
            ];
        }
    }

    // Current user message
    $geminiContents[] = [
        'role' => 'user',
        'parts' => [
            ['text' => $message]
        ]
    ];

    $geminiPayload = [
        'systemInstruction' => [
            'parts' => [
                ['text' => $systemInstruction]
            ]
        ],
        'contents' => $geminiContents,
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 2048,
            'topP' => 0.95,
            'thinkingConfig' => [
                'thinkingBudget' => 0
            ]
        ]
    ];
    $jsonPayload = json_encode($geminiPayload);

    foreach ($modelsToTry as $m) {
        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key=" . urlencode($geminiApiKey);

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: SweetPop-Gemini-Chatbot/1.0'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($response)) {
            $respData = json_decode($response, true);
            $extractedText = $respData['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (!empty($extractedText)) {
                $geminiReply = trim($extractedText);
                break;
            }
        }
    }
}

// If Gemini generated a reply successfully:
if ($geminiReply !== null) {
    // Check if the reply is an out-of-scope refusal
    $isOos = (mb_strpos($geminiReply, 'Maaf ya Kak') !== false || mb_strpos($geminiReply, 'di luar') !== false && mb_strpos($geminiReply, 'SweetPop') !== false);

    // Log to DB
    $logStmt = mysqli_prepare($con, "INSERT INTO ai_chat_logs (session_id, user_id, user_message, bot_response, intent, is_out_of_scope) VALUES (?, ?, ?, ?, 'gemini_flash_ai', ?)");
    $uid = current_user_id() ?: null;
    $oosInt = $isOos ? 1 : 0;
    mysqli_stmt_bind_param($logStmt, "sissi", $sessionId, $uid, $message, $geminiReply, $oosInt);
    mysqli_stmt_execute($logStmt);

    $suggestions = [
        '🍰 Menu Best Seller',
        '🚚 Ketentuan Jadwal Kirim',
        '🏷️ Voucher Diskon 20%',
        '🎂 Custom Ucapan & Lilin'
    ];

    echo json_encode([
        'status' => 'success',
        'reply' => $geminiReply,
        'is_out_of_scope' => $isOos,
        'is_gemini' => true,
        'suggestions' => $suggestions
    ]);
    exit;
}

// ========================================================
// 4. ZERO-FAILURE FALLBACK: LOCAL KMS ENGINE
// (Executes if Gemini API is offline or quota reached)
// ========================================================

// 4.1 Product Specific Title Match
$productMatch = null;
foreach ($allActiveProducts as $p) {
    $titleLower = mb_strtolower($p['product_title'], 'UTF-8');
    if (mb_strpos($lowerMsg, $titleLower) !== false) {
        $productMatch = $p;
        break;
    }
}

if ($productMatch) {
    $priceFormatted = "Rp " . number_format($productMatch['product_price'], 0, ',', '.');
    $reply = "🍓 **" . htmlspecialchars($productMatch['product_title']) . "**\n\n"
           . "• **Kategori:** " . htmlspecialchars($productMatch['cat_title']) . "\n"
           . "• **Harga:** **" . $priceFormatted . "**\n"
           . "• **Ketersediaan:** " . ($productMatch['product_stock'] > 0 ? "✅ Ready Fresh (" . $productMatch['product_stock'] . " porsi)" : "⚠️ Pre-Order / Terbatas") . "\n"
           . "• **Deskripsi Rasa:** " . htmlspecialchars($productMatch['product_description'] ?: 'Dibuat dengan bahan-bahan premium pilihan dan selalu freshly baked!') . "\n\n"
           . "Mau langsung pesan? Kakak bisa klik [Lihat Detail & Masukkan Keranjang](product.php?id=" . $productMatch['product_id'] . ") ya! ♡";

    $logStmt = mysqli_prepare($con, "INSERT INTO ai_chat_logs (session_id, user_id, user_message, bot_response, intent, is_out_of_scope) VALUES (?, ?, ?, ?, 'product_detail', 0)");
    $uid = current_user_id() ?: null;
    mysqli_stmt_bind_param($logStmt, "siss", $sessionId, $uid, $message, $reply);
    mysqli_stmt_execute($logStmt);

    echo json_encode([
        'status' => 'success',
        'reply' => $reply,
        'is_out_of_scope' => false,
        'is_gemini' => false,
        'suggestions' => ['🛒 Buka Keranjang Belanja', '🚚 Jam Pengantaran', '🏷️ Cek Promo Diskon']
    ]);
    exit;
}

// 4.2 Handling questions about recipes / baking in fallback
if (preg_match('/\b(resep|cara\s+buat|cara\s+bikin|cara\s+membuat|baking|adonan)\b/iu', $lowerMsg)) {
    $reply = "Halo Kak! 🧁 Resep racikan khas dapur **SweetPop** adalah rahasia dapur bakehouse kami yaa hihi 👩‍🍳✨\n\n"
           . "Tapi jangan khawatir, semua dessert kami dijamin menggunakan **100% bahan premium pilihan**:\n"
           . "• **100% Halal** tanpa rum, gelatin non-halal, atau alkohol\n"
           . "• Menggunakan *real butter* berkualitas tinggi & susu sapi segar (*pure dairy*)\n"
           . "• Cokelat murni (*pure cocoa & dark compound chocolate*)\n"
           . "• Higienis, *freshly baked* setiap hari tanpa bahan pengawet buatan!\n\n"
           . "Daripada repot memanggang dan berantakan di dapur, Kakak bisa langsung memesan dessert favorit siap santap di SweetPop lho! Cek menu lezat kami di [Katalog Menu](store.php) ya! 🍰🍓";

    $logStmt = mysqli_prepare($con, "INSERT INTO ai_chat_logs (session_id, user_id, user_message, bot_response, intent, is_out_of_scope) VALUES (?, ?, ?, ?, 'recipe_inquiry', 0)");
    $uid = current_user_id() ?: null;
    mysqli_stmt_bind_param($logStmt, "siss", $sessionId, $uid, $message, $reply);
    mysqli_stmt_execute($logStmt);

    echo json_encode([
        'status' => 'success',
        'reply' => $reply,
        'is_out_of_scope' => false,
        'is_gemini' => false,
        'suggestions' => ['🍰 Menu Best Seller', '🛒 Buka Katalog Menu', '❄️ Info Bahan & Alergen']
    ]);
    exit;
}

// 4.3 Knowledge Semantic & Keyword Scoring (Word-Boundary + Stopwords Filtering)
$bestScore = 0;
$bestKms = null;

$stopWords = [
    'yang', 'untuk', 'pada', 'ke', 'dari', 'di', 'dan', 'atau', 'ini', 'itu', 'juga', 
    'sudah', 'telah', 'akan', 'bisa', 'dapat', 'ada', 'adalah', 'apa', 'apakah', 
    'siapa', 'kapan', 'dimana', 'bagaimana', 'kenapa', 'mengapa', 'berapa', 'tau', 
    'tahu', 'boleh', 'kah', 'nya', 'saja', 'dong', 'sih', 'kok', 'deh', 'ya', 'yaa', 
    'kak', 'kakak', 'min', 'admin', 'saya', 'aku', 'kami', 'mau', 'ingin', 'tolong', 
    'mohon', 'punya', 'buat', 'bikin', 'tentang', 'seperti', 'sama', 'tentu', 'ga', 'ngga', 'nggak'
];

$cleanQuery = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $lowerMsg);
$rawWords = array_filter(explode(' ', $cleanQuery), function($w) {
    return mb_strlen($w) >= 3;
});
$queryWords = array_diff($rawWords, $stopWords);

foreach ($kmsItems as $item) {
    $score = 0;
    $keywords = mb_strtolower($item['keywords'], 'UTF-8');
    $title = mb_strtolower($item['title'], 'UTF-8');
    $content = mb_strtolower($item['content'], 'UTF-8');
    $category = mb_strtolower($item['category'], 'UTF-8');

    // Exact phrase match in keywords or title
    if (mb_strpos($keywords, $lowerMsg) !== false || mb_strpos($lowerMsg, $title) !== false) {
        $score += 25;
    }

    // Word boundary matching for query words
    foreach ($queryWords as $qw) {
        $wordPattern = '/\b' . preg_quote($qw, '/') . '\b/iu';
        if (preg_match($wordPattern, $keywords)) $score += 8;
        if (preg_match($wordPattern, $title)) $score += 6;
        if (preg_match($wordPattern, $category)) $score += 4;
        if (preg_match($wordPattern, $content)) $score += 2;
    }

    if ($score > $bestScore) {
        $bestScore = $score;
        $bestKms = $item;
    }
}

if ($bestKms && $bestScore >= 12) {
    $cleanTitle = html_entity_decode(htmlspecialchars_decode($bestKms['title']), ENT_QUOTES, 'UTF-8');
    $cleanContent = html_entity_decode(htmlspecialchars_decode($bestKms['content']), ENT_QUOTES, 'UTF-8');

    $reply = "🧁 **" . htmlspecialchars($cleanTitle) . "**\n\n"
           . $cleanContent . "\n\n"
           . "Ada hal lain seputar menu atau pesanan SweetPop yang ingin Kakak tanyakan? 🎀";

    $logStmt = mysqli_prepare($con, "INSERT INTO ai_chat_logs (session_id, user_id, user_message, bot_response, intent, is_out_of_scope) VALUES (?, ?, ?, ?, 'kms_matched', 0)");
    $uid = current_user_id() ?: null;
    mysqli_stmt_bind_param($logStmt, "siss", $sessionId, $uid, $message, $reply);
    mysqli_stmt_execute($logStmt);

    echo json_encode([
        'status' => 'success',
        'reply' => $reply,
        'is_out_of_scope' => false,
        'is_gemini' => false,
        'suggestions' => ['🍰 Menu Best Seller', '🚚 Ketentuan Jadwal Kirim', '🏷️ Kupon Diskon SWEET20']
    ]);
    exit;
}


// 4.3 Friendly General Greeting Fallback
$reply = "Halo Kak! 🧁 Aku **Sweetie AI**, asisten cerdas resmi dari **SweetPop** (*Aesthetic Dessert & Bakery Store*).\n\n"
       . "Aku siap membantu Kakak seputar:\n"
       . "• Rekomendasi menu kue perayaan & dessert box kekinian\n"
       . "• Informasi jadwal pengantaran & promo gratis ongkir min. Rp 50K\n"
       . "• Personalisasi ucapan kue & lilin ulang tahun gratis\n"
       . "• Tips penyimpanan kue agar tetap fresh & lezat\n\n"
       . "Kira-kira Kakak sedang mencari dessert untuk acara apa nih? 🍰✨";

$logStmt = mysqli_prepare($con, "INSERT INTO ai_chat_logs (session_id, user_id, user_message, bot_response, intent, is_out_of_scope) VALUES (?, ?, ?, ?, 'greeting', 0)");
$uid = current_user_id() ?: null;
mysqli_stmt_bind_param($logStmt, "siss", $sessionId, $uid, $message, $reply);
mysqli_stmt_execute($logStmt);

echo json_encode([
    'status' => 'success',
    'reply' => $reply,
    'is_out_of_scope' => false,
    'is_gemini' => false,
    'suggestions' => [
        '🍰 Menu Best Seller',
        '🚚 Jadwal & Ongkir',
        '🎂 Custom Ucapan & Lilin',
        '🏷️ Kupon Diskon 20%'
    ]
]);
