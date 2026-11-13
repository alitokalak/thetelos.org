# thetelos.org — çalışma notları (yeni oturum için)

## İletişim
- Kullanıcıyla **kısa, net Türkçe**. Adım adım, tek seferde bir iş. Uzun/karışık açıklama yok, bahane yok.
- Kullanıcı teknik değil: "şu adresi aç", "şu düğmeye bas" gibi somut adımlar ver.
- Tahminle konuşma; önce koddan / canlı siteden / veriden doğrula, sonra söyle. Bilmediğini söyle.

## Yığın ve yayın
- WordPress (Mediumish/TheTelos teması, bu repo kökü) + özel PHP paneli `thetelos-panel/`.
- Hosting: cPanel (akwebhostingi), sunucu IP 95.173.165.196, PHP 8.3, LiteSpeed Cache.
- Cloudflare: thetelos.org **gri bulut (DNS only)**. Mail: cPanel (MX → mail.thetelos.org, SPF `v=spf1 +a +mx +ip4:95.173.165.196 ~all`). Site mailleri PHP mail() ile.
- Çalışma dalı: `claude/proje-detaylari-rye6r5`. **Push = GitHub Actions FTP ile canlıya yayın.** Her değişiklikte `php -l` / `node --check`, commit, push.
- Sırlar repoya GİRMEZ (`thetelos-panel/config.php`, `*.secret.php`, R2/TLS anahtarları). Sohbette parola/anahtar isteme.

## Maliyet kuralları (kullanıcı çok hassas)
- Ana model **DeepSeek** (`deepseek-flash`). Claude yalnız son çare: **Haiku 5.5, Batch API (−%50)**.
- **Sonnet ve Claude web araması kullanılmaz** (5 kitap ≈ 2$ gitmişti). Haiku seçiliyken web araması kodda kapalı (`$GLOBALS['bw_claude_lean']`).
- Panelde üstteki API satırı: Toplu yazdırmada **DeepSeek** seçilmeli. "Anthropic Batch" seçilirse ana yazar Claude olur (daha pahalı).

## Toplu yazdırma sırası (DeepSeek modu, `api/batch-worker.php`)
1. Kitabın tam metni (Gutenberg/IA/Standard Ebooks/OL-IA/Gallica) → DeepSeek özet. **Başlık birebir eşleşmeli** (`proto_title_strict`), benzer/kardeş eser asla; OL'de yazar **ilk sırada** olmalı.
2. DeepSeek kesin biliyorsa → düşünerek (`ds_think`) geniş özet.
3. Bilmiyorsa → Claude Haiku (beklemeli batch, aramasız): biliyorsa geniş, kısmen biliyorsa bildiği kadar, uydurmasız.
4. Claude da bilmiyorsa (`claude_unknown`) → DeepSeek internet araması (İngilizce + orijinal adla, `_dsweb.php`) ile kısa Bilgi Metni (düşünerek).
5. O da yoksa → yer tutucu sayfa: DeepSeek kısa yazar yazısı + gizli işaret (`is being prepared…` HTML yorumunda) → **noindex**, site haritası dışı.
- Yayın hızı: **günde en fazla 30** yeni kitap (zamanlanmış yayın, `cache/pub-rate.json`); 7 günlük sıra dolunca yazma bekler. Yer tutucular sıraya girmez.

## İçerik kuralları
- Uydurma yok. Derleme kitap tek yazara atfedilmez (Claviez/Žižek olayı).
- Başlıklar doğal ve kitaba özgü; "Chapter/Part/Book N", "Overview", "Key Ideas", "Why It Matters", "Inside the Book" vb. yasak (prompt + `bw_clean_content` temizliği).
- Model notu ("I'll start by searching…") yazıya sızmaz (`tls_strip_search_preamble`).
- Meta/excerpt: tek tam cümle, ≤160 karakter (`_meta-rules.php`), kod asla kırpmaz.

## Google durumu (Kasım 2026)
- 17–18 Ekim'de gösterimler bir günde %92 düştü; teknik sorun yok, manuel işlem yok → otomatik spam (ölçekli içerik) kararı.
- 9 Kasım: 2.673 yazı noindex yapıldı (0 okunma + Search Console'da yok). Geri alma: panel girişliyken `/thetelos-panel/api/seo-prune.php?revert=1`.
- **Aralık sonu – Ocak başı** Search Console birlikte değerlendirilecek (gösterim, dizindeki sayfa). İyileşme yoksa gizleme geri alınıp başka yol düşünülecek.

## Açık işler
- `/thetelos-panel/api/fix-preamble.php?apply=1` (14 yazının başındaki model notu) — kullanıcı çalıştırdı mı kontrol et.
- Eski düz "yakında" yer tutucuları DeepSeek yazar yazısıyla doldurma (öneri, onay bekliyor).
- Haziran–Temmuz'dan kalan hiç yeniden yazılmamış yazılar (en zayıf grup) — Aralık değerlendirmesinden sonra.
- Sesli dinleme: anlık (DeepInfra) **kapalı**; yalnız Colab (ücretsiz Kokoro) çalışıyor. Yorum/puan sistemi: başlanmadı.

## Faydalı araçlar (panel oturumu gerekir)
- `api/seo-inventory.php` (yazı başına okunma/noindex, salt okunur), `api/seo-prune.php`, `api/fix-preamble.php`, `api/fix-claviez.php` (tek seferlik, uygulandı).
