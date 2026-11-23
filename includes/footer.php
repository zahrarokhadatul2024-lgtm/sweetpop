    <!-- Footer -->
    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <!-- Brand Col -->
                <div class="footer-col">
                    <a href="index.php" class="logo" style="margin-bottom: 14px; display: inline-block;">
                        <i class="fa-solid fa-cake-candles"></i> SWEET<span>POP</span>
                    </a>
                    <p>Dessert kecil dengan kebahagiaan yang besar. Made with sugar, love & a little bit of magic in Yogyakarta for your main-character era 🍰🩵🎀</p>
                    <div class="social-links">
                        <a href="https://instagram.com" target="_blank" class="social-btn" aria-label="Instagram">
                            <i class="fa-brands fa-instagram"></i>
                        </a>
                        <a href="https://tiktok.com" target="_blank" class="social-btn" aria-label="TikTok">
                            <i class="fa-brands fa-tiktok"></i>
                        </a>
                        <a href="https://api.whatsapp.com/send?phone=<?= format_wa_number(get_store_settings()['whatsapp']); ?>&text=<?= urlencode('Halo SweetPop! 🍰 Mau tanya tentang menu dessert ♡'); ?>" target="_blank" class="social-btn" aria-label="WhatsApp">
                            <i class="fa-brands fa-whatsapp"></i>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="footer-col">
                    <h4>Categories</h4>
                    <ul class="footer-links">
                        <li><a href="store.php?category=1">Aesthetic Cakes</a></li>
                        <li><a href="store.php?category=2">Dessert Boxes</a></li>
                        <li><a href="store.php?category=3">Chewy Cookies</a></li>
                        <li><a href="store.php?category=4">Fudgy Brownies</a></li>
                        <li><a href="store.php?category=5">Silky Pudding</a></li>
                        <li><a href="store.php?category=6">Artisan Drinks</a></li>
                    </ul>
                </div>

                <!-- Customer Service -->
                <div class="footer-col">
                    <h4>Customer Care</h4>
                    <ul class="footer-links">
                        <li><a href="cart.php">Shopping Bag</a></li>
                        <li><a href="profile.php">Track My Order</a></li>
                        <li><a href="store.php">Custom Cake Orders</a></li>
                        <li><a href="admin/login.php" style="color: var(--pink-dark); font-weight:600;"><i class="fa-solid fa-lock"></i> Admin Portal</a></li>
                    </ul>
                </div>

                <!-- Opening Hours & Location -->
                <div class="footer-col">
                    <h4>Bakehouse & Store</h4>
                    <p><i class="fa-solid fa-location-dot" style="color:var(--pink);"></i> Jl. Kaliurang KM 5, Sleman, D.I. Yogyakarta</p>
                    <p><i class="fa-regular fa-clock" style="color:var(--blue-dark);"></i> Open Daily: 09.00 - 21.00 WIB</p>
                    <p><i class="fa-brands fa-whatsapp" style="color:#10b981;"></i> <?= htmlspecialchars(get_store_settings()['whatsapp']); ?></p>
                </div>
            </div>

            <div class="footer-bottom">
                <p>© <?= date('Y'); ?> <strong>SWEETPOP Inc.</strong> All rights reserved. Crafted with ♡ for Praktikum Pemrograman Web.</p>
            </div>
        </div>
    </footer>

    <?php
    $storeSettings = get_store_settings();
    $waNumber = format_wa_number($storeSettings['whatsapp']);
    ?>

    <!-- Floating WhatsApp Support Widget -->
    <div class="floating-wa-container" style="position:fixed; bottom:24px; right:24px; z-index:9999; display:flex; align-items:center; gap:10px;">
        <div class="wa-tooltip" style="background:#ffffff; border:1px solid var(--pink-light); box-shadow:0 8px 24px rgba(230,76,116,0.15); padding:8px 14px; border-radius:20px; font-size:0.85rem; font-weight:600; color:var(--text); cursor:pointer; display:none;" id="waTooltip" onclick="window.open('https://api.whatsapp.com/send?phone=<?= $waNumber; ?>&text=<?= urlencode('Halo SweetPop! 🍰 Saya ingin bertanya seputar rekomendasi menu dessert hari ini ♡'); ?>', '_blank')">
            <span>🍰</span> Butuh bantuan pesanan? <strong>Chat CS</strong>
        </div>
        <a href="https://api.whatsapp.com/send?phone=<?= $waNumber; ?>&text=<?= urlencode('Halo SweetPop! 🍰 Saya ingin bertanya seputar rekomendasi menu dessert hari ini ♡'); ?>" target="_blank" class="floating-wa-btn" style="width:56px; height:56px; border-radius:50%; background:#25D366; color:#ffffff; display:flex; align-items:center; justify-content:center; font-size:1.8rem; box-shadow:0 6px 20px rgba(37,211,102,0.45); text-decoration:none; transition:transform 0.2s;" title="Chat WhatsApp SweetPop" onmouseover="document.getElementById('waTooltip').style.display='flex';" onmouseout="document.getElementById('waTooltip').style.display='none';">
            <i class="fa-brands fa-whatsapp"></i>
        </a>
    </div>

    <!-- ======================================================== -->
    <!-- SWEETIE AI CHATBOT WIDGET                                -->
    <!-- ======================================================== -->
    <div class="sweetie-ai-launcher">
        <div class="sweetie-ai-tooltip" id="sweetieTooltip">
            <span>🧁</span> <strong>Sweetie AI:</strong> Mau tanya rekomendasi kue? ♡
        </div>
        <button type="button" class="sweetie-ai-btn" id="sweetieLauncherBtn" title="Chat dengan Sweetie AI (Asisten Cerdas SweetPop)" aria-label="Sweetie AI Chatbot">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
            <span class="sparkle-badge">AI</span>
        </button>
    </div>

    <!-- Sweetie AI Chatbot Window -->
    <div class="sweetie-ai-window" id="sweetieChatWindow">
        <!-- Header -->
        <div class="sweetie-ai-header">
            <div class="sweetie-ai-avatar-wrapper">
                <div class="sweetie-ai-avatar">
                    <i class="fa-solid fa-cake-candles"></i>
                    <span class="online-pulse"></span>
                </div>
                <div class="sweetie-ai-title-wrap">
                    <h4>Sweetie AI ✦ SweetPop</h4>
                    <p><i class="fa-solid fa-circle-check" style="color:#4ade80;"></i> Online • Siap Membantu ✿</p>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <button type="button" class="sweetie-ai-reset-btn" id="sweetieResetBtn" title="Mulai Obrolan Baru (Reset Percakapan)" aria-label="Reset Chat">
                    <i class="fa-solid fa-rotate-right"></i>
                </button>
                <button type="button" class="sweetie-ai-close-btn" id="sweetieCloseBtn" title="Tutup Chat" aria-label="Close Chat">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <!-- Chat Body -->
        <div class="sweetie-ai-body" id="sweetieChatBody">
            <!-- Default Welcome Bot Message -->
            <div class="chat-msg bot">
                <div style="width:30px; height:30px; border-radius:50%; background:linear-gradient(135deg, #ba007c, #ff7597); color:#fff; display:flex; align-items:center; justify-content:center; font-size:0.9rem; flex-shrink:0; margin-top:2px;">
                    <i class="fa-solid fa-cake-candles"></i>
                </div>
                <div style="flex:1;">
                    <div class="chat-bubble">
                        Halo Kak! 🧁 Aku <strong>Sweetie</strong>, asisten virtual resmi toko kue <strong>SweetPop</strong>.<br><br>
                        Aku siap membantu Kakak seputar menu dessert lezat kami, jadwal pengantaran, cara pemesanan, hingga rekomendasi kue spesial!<br><br>
                        Ada yang bisa Sweetie bantu hari ini? 🎀
                        <span class="chat-time">Sweetie • Online</span>
                    </div>
                    <div class="sweetie-ai-suggestions">
                        <button type="button" class="ai-chip" onclick="window.sweetieAsk('🍰 Menu Best Seller SweetPop')">🍰 Menu Best Seller</button>
                        <button type="button" class="ai-chip" onclick="window.sweetieAsk('🚚 Ketentuan Jam & Slot Pengantaran')">🚚 Slot Pengantaran</button>
                        <button type="button" class="ai-chip" onclick="window.sweetieAsk('🏷️ Cara Dapat Promo Gratis Ongkir')">🏷️ Promo Gratis Ongkir</button>
                        <button type="button" class="ai-chip" onclick="window.sweetieAsk('🎂 Personalisasi Ucapan & Lilin Gratis')">🎂 Custom Ucapan & Lilin</button>
                        <button type="button" class="ai-chip" onclick="window.sweetieAsk('❄️ Tips Simpan Kue di Kulkas')">❄️ Tips Simpan Kue</button>
                    </div>
                </div>
            </div>

            <!-- Typing indicator element -->
            <div class="sweetie-typing" id="sweetieTypingIndicator">
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
                <span style="font-size:0.75rem; color:#9d174d; font-weight:600; margin-left:4px;">Sweetie sedang menyiapkan jawaban... 💭</span>
            </div>
        </div>

        <!-- Input Footer -->
        <div class="sweetie-ai-footer">
            <form class="sweetie-ai-form" id="sweetieChatForm">
                <input type="text" class="sweetie-ai-input" id="sweetieChatInput" placeholder="Tanya menu, ongkir, custom cake..." autocomplete="off">
                <button type="submit" class="sweetie-ai-send-btn" id="sweetieSendBtn" title="Kirim Pesan" aria-label="Send">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>
            <span class="sweetie-disclaimer">
                ✦ Sweetie AI hanya merespons topik seputar toko kue SweetPop.
            </span>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="sweet-toast"></div>

    <!-- jQuery & FlexSlider Libraries (Bab IX Component) -->
    <script src="assets/js/jquery-3.7.1.min.js"></script>
    <script src="assets/js/jquery.flexslider-min.js"></script>

    <!-- Main JavaScript & Sweetie AI Chatbot Client -->
    <script src="assets/js/main.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/chatbot.js"></script>
</body>
</html>
