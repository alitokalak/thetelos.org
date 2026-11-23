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

## YALNIZ CLAUDE MODU (19 Kasım 2026, panelde 🤖 kutu, varsayılan KAPALI)
- Açıkken: tam metin ARANMAZ, DeepSeek yazmaz. Her kitap doğrudan **Claude Haiku 5.5 beklemeli Batch** (−%50: $0.05/$0.25 /1M — DeepSeek gece fiyatından ucuz), katı "kesin bilgi" istemi; bilmiyorsa atlanır. Çift kayıt sorusu da Claude Haiku'ya gider. DeepSeek yoğun saat beklemesi yok (worker, cron-tick, panel uyarısı). Meta/kategori gibi küçük yan işler hâlâ DeepSeek.
- **TEK TEK (kullanıcı kuralı, kesin):** yalnız Claude modunda aynı anda TEK kitap: Claude'a gider → cevap gelir → yazılır/atlanır → ancak sonra sıradaki (`bw_claim_next`, `processing` varken ve `claude_wait` varken yeni kitap alınmaz). Kullanıcı toplu gönderime çok kızdı; paralelleştirme önerme.
- Çift kayıt denetimi listedeki ÖNCEDEN gönderilmiş/yazılmış kitaplara da bakar ("Listede tekrar — atlandı").
- 19.11: ilk denemede Haiku ünlü kitapları bile (Bloch "Principle of Hope", Mayr "Growth of Biological Thought") "UNKNOWN" dedi → istem dengelendi ("biliyorsan yaz, aşırı temkin yok; bilmiyorsan UNKNOWN"), Haiku **düşünerek** (adaptive, +4000 max_tokens), kısa eşik 150 kelime; atlananlar `cl_requeue_v3` ile bir kez yeniden soruldu. Ekranda atlama sebebi açık yazılır (UNKNOWN / kısa N kelime / batch hatası).
- Uzunluk = **ESKİ PARÇA SİSTEMİ (kullanıcı kuralı, kesin):** Haiku tek cevapta ~1.800 kelimede durur → hedef ~1.800'lük parçalara bölünür (Yalnız Claude modunda hedef HER ZAMAN 6.000 = 4 × 1.500; paneldeki kelime ayarı yok sayılır — 22.11: varsayılan 3.500 yüzünden 2 parça yazılıyordu). Parça 1 kitabın BAŞINI yazar, parça 2..N kitabın KENDİ SIRASIYLA kesintisiz devamı (biten başlıklar + metnin son 900 karakteri verilir; kronoloji korunur), son parça kitabın sonuna kadar tamamlar. Bilip bilmediğine YALNIZ parça 1'de karar verilir (UNKNOWN → atla); biliyorsa 2..N parçaların HEPSİ yazılır, ortada durma seçeneği yok (kullanıcı kuralı). Her parça ayrı beklemeli istek; kitap tüm parçalar bitene kadar claude_wait'te (`bw_claude_next_part`, `claude_parts/claude_part/claude_acc`). "Sona eksik bölüm ekle" ve "tamamını uzat" yöntemleri denendi, kronolojiyi bozduğu/işe yaramadığı için KALDIRILDI.
- Cilt/baskı/katalog satırları ("Gesamtausgabe … Band N", "Werke Bd.", "[microform]", başlık = yazar adı) hiç yazılmaz, baştan `skipped` (22.11 Bolzano listesi). "Sitede var #X" etiketi aynı külliyatın başka bir cildi yazılmışsa da çıkar.
- Ayar: `settings.json` `claude_only` (açıkken `strict_mode` da açık sayılır).

## KESİN BİLGİ MODU (18 Kasım 2026, varsayılan AÇIK — panelde API satırındaki 🎯 kutu)
- Sıra: tam metin → DeepSeek kaynaktan yazar. Yoksa **doğrudan Claude Haiku** (beklemeli batch, aramasız, katı istem `strict`): kitabın gerçek içeriğini kesin biliyorsa yazar (kısmen biliyorsa yalnız bildiğini; <150 kelime = açılmaz). Bilmiyorsa kitap **atlanır** (`skipped`). DeepSeek hafıza özeti, Bilgi Metni, kısa not, yer tutucu AÇILMAZ.
- Özet/bilgi tipli toplu işler de bu yola girer (tekli üretim ve "Anthropic" seçimi hariç). Ayar: `settings.json` `strict_mode`.
- **Çift kayıt engeli** (`api/_dup-guard.php`, her yeni yazıda): sitede aynı yazarın tüm yazıları toplanır; parantezdeki orijinal ad / alt başlık / yazım farkı kodla, çeviri-farklı ad-aynı derleme DeepSeek ile yakalanır → yeni sayfa açılmaz.
- DeepSeek'e yalnız çeviri ihtimali (yabancı başlık: aksan/Kiril/pinyin/yabancı durak kelime) ya da %60+ kelime örtüşmesi olan adaylar sorulur; iki farklı İngilizce başlık asla "aynı" sayılmaz (19.11: Mayr'ın farklı kitapları yanlışlıkla atlanmıştı → düzeltildi, `dg_requeue_v2` ile yeniden sıraya alındı).
- Ölüm döngüsü koruması: bir kitap işlenirken süreç 3 kez ölürse `error` olur, sıradakine geçilir (`deaths`). (19.11: "The logic of life" 13 saat kilitlemişti.)
- "Sitede zaten olan yazarları atla" kutusu açıkken o yazarların TÜM satırları listeden düşer (ör. Quesnay).
- Kullanıcı günlük sınırı düşürmek istemiyor (30 kalsın; "5" önerisi reddedildi).

## Toplu yazdırma sırası (ESKİ DeepSeek modu — kesin bilgi modu KAPALIYSA, `api/batch-worker.php`)
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

## Üye mailleri (23.11.2026)
- Panel → Araçlar → **Üye Mailleri** (`thetelos-panel/member-digest.php`, `api/member-digest.php`); mantık temada `inc/member-digest.php`. ELLE gönderim (otomatik yok, kullanıcı kararı).
- Öneri: işaretli kitaplar (want/reading/read) + kayıtlı özetler + ilgi alanlarının kategorileri → işaretlenmemiş, noindex/yer tutucu olmayan, ≥2.000 kelimelik özetlerden 5 (aynı yazardan ≤2) + "Want to Read" hatırlatması (≤3). Yapay zekâ yok.
- Mail İngilizce, HTML; abonelikten çıkış linki `?tls_unsub=UID&k=` → `_tls_digest_optout`. Test maili thetelosorg@gmail.com adresine gider (`TLS_DIGEST_TEST_TO`). Her öneride kitap kapağı (öne çıkan görsel; yoksa sitenin ürettiği kapak). Gezinme geçmişi TUTULMUYOR (istenirse eklenir + gizlilik metni).

- Üye kendisi de yönetir: Profil → Settings → **Email Preferences** kutusu (ajax `tls_digest_pref`): kapatınca `_tls_digest_optout`, açınca silinir + `_tls_digest_optin` tarihi. Mailde Unsubscribe en üstte ve en altta; linke tıklama yalnız ONAY sayfası açar, çıkış POST ile (23.11: mail-tester link denetimi üyeyi habersiz çıkarmıştı); Gmail tek tık (`List-Unsubscribe-Post`) doğrudan çıkarır. Panelde çıkanlar için "↺ Geri aç".

- **Dönüş ölçümü:** gerçek gönderimde maildeki linkler `?tls_go=PID&u=UID&i=SAYI&k=` → tıklama `_tls_digest_clicks`'e (bot UA elenir; 10 sn'de 3+ tık = şüpheli) → UTM'li sayfaya yönlenir (`utm_source=member_digest`, `utm_campaign=issue_N`, Analytics'te görünür). Test/önizleme takip edilmez. Panel: son sayı özeti (kaç kişiye gitti / kaç kişi geldi %) + satırda kim geldi, hangi kitap. Açılma (pixel) takibi YOK (güvenilmez).

## Faydalı araçlar (panel oturumu gerekir)
- `api/seo-inventory.php` (yazı başına okunma/noindex, salt okunur), `api/seo-prune.php`, `api/fix-preamble.php`, `api/fix-claviez.php` (tek seferlik, uygulandı).
