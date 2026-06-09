THETELOS CONTENT PANEL — Kurulum Talimatları
=============================================

1. config.php dosyasını açın ve doldurun:
   - WP_URL       → https://thetelos.org
   - WP_USER      → WordPress kullanıcı adınız
   - WP_APP_PASS  → Application Password (aşağıda açıklanmıştır)
   - ANTHROPIC_KEY → sk-ant-... anahtarınız
   - PANEL_PASSWORD → panele giriş şifreniz

2. Application Password nasıl oluşturulur:
   - WP Admin → Kullanıcılar → Profilim → Application Passwords
   - "Content Panel" gibi bir isim verin → Oluştur
   - Çıkan şifreyi boşluklarla birlikte WP_APP_PASS'e yapıştırın

3. Tüm klasörü FTP ile thetelos.org/panel/ klasörüne yükleyin

4. https://thetelos.org/panel/ adresine gidin

5. Ayarlar sayfasında prompt şablonlarınızı girin

KLASÖR YAPISI
─────────────
index.php          → Giriş sayfası
panel.php          → Ana panel (Tek kitap + Toplu)
settings.php       → Prompt & bağlantı ayarları
config.php         → Yapılandırma (doldurun!)
prompts.json       → Prompt şablonları (Ayarlar'dan düzenlenir)
api/
  generate.php     → Anthropic API çağrısı
  publish.php      → WP REST API ile yayınlama
  bulk-upload.php  → CSV/XLSX parser
  bulk-process.php → Toplu kitap işleme
  settings.php     → Prompt kaydetme / bağlantı testi
assets/
  style.css        → Arayüz stilleri
  app.js           → Frontend JS

NOTLAR
──────
- Yoast SEO meta description otomatik yazılır (show_in_rest gerekli)
- Kitap kapakları Open Library'den otomatik çekilir
- Authors taxonomy otomatik oluşturulur
- CSV formatı: Kitap Adı, Yazar, Kategori (başlık satırı opsiyonel)
