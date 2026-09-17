# THETELOS.ORG — Devir / Handoff Notu

> Bu dosya `.github/` altında olduğu için **deploy'a dahil değil** (web'e açılmaz),
> ama repoyu klonlayan her oturum görür. **Sır değeri yoktur; sadece isimler geçer.**

## Proje
- WordPress kitap-özeti sitesi (~7.600 yazı, ~396 yazar, 220+ kategori).
- **Tema** repo kök dizininde (Mediumish türevi "TheTelos").
- **Özel PHP admin paneli** `thetelos-panel/` altında.
- Özel taxonomy: **`authors`** (yazar arşivleri). Ayrıca standart `category`.

## Repo & Dal
- GitHub: **alitokalak/thetelos.org**
- **Yalnızca `claude/modest-goodall-ow96d3` dalında** geliştir ve push et. Başka dala push yok.
- GitHub işlemleri **GitHub MCP araçlarıyla** (`mcp__github__*`); `gh` CLI yok.

## Deploy
- `.github/workflows/deploy.yml` — push'ta (main/master/`claude/**`) tetiklenir, **FTP** ile yükler, ~birkaç dk.
- **İki bağımsız hedef:** (1) Tema → kök (`FTP_THEME_PATH`, `thetelos-panel` hariç), (2) Panel → `thetelos-panel/` (`FTP_PANEL_PATH`). Her hedef 2 kez denenir.
- Sırlar **GitHub Secrets**'ta: `FTP_HOST`, `FTP_USER`, `FTP_PASS`, `FTP_THEME_PATH`, `FTP_PANEL_PATH`. **Değerleri repoda/chatte YOK, olmamalı.**
- Tema adımı `continue-on-error` (yalnız panel adımı doğrulanır). Deploy durumunu GitHub Actions MCP araçlarıyla oku.
- Panel sunucuda `wp-load.php`'i `/home/thetelos/public_html/wp-load.php`'ten yükler.

## Cache (KRİTİK — "değişmiyor" sorununun kaynağı)
- Site **Cloudflare + LiteSpeed** arkasında. Deploy çıksa bile eski HTML cache'lenir.
- Cache-bypass testi: URL'ye `?x=rastgele` ekle. Kalıcı için Cloudflare + LiteSpeed **purge** + tarayıcıda **Cmd+Shift+R**.

## Ajan ortamı kısıtı
- thetelos.org'a **giden ağ erişimi kapalı** (curl 000/403). Siteyi doğrudan gezemezsin;
  doğrulama için **GitHub Actions logları** + kullanıcının **ekran görüntüleri**.

## Değişmez kurallar
- `thetelos-panel/config.php` **canlı sırları tutar** — asla commit etme, asla chatte yazdırma.
- **Asla içerik uydurma** ("sallama yok").
- **Anthropic/Claude API kredisi bitti** → panel araçlarında yalnız **DeepSeek**.
  Çağrı hep `thetelos-panel/api/_verify.php` içindeki
  **`tv_ask($prompt,$max,$timeout,'deepseek')`** üzerinden (thinking kapalı,
  `reasoning_content` yedeği, OpenRouter erişimi). Doğrudan DeepSeek çağrısı çoğu kez boş döner — kullanma.
- Commit/PR/kod/yorumda **model adı geçmesin** (sadece chatte).
- Commit footer: `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>` + `Claude-Session: <oturum linki>`.

## Mimari & teknik detaylar
- **14 kalıcı ANA KATEGORİ** (kitapçı/BISAC mantığı) altında 220+ düz kategori gruplanır.
  Gruplama **sunum katmanında**: `get_option/update_option` →
  `tls_cat_group_of` (`[term_id => main_slug]`), `tls_cat_groups`, `tls_cat_main_labels`.
  **WP parent DEĞİŞMEZ → kategori URL'leri değişmez, SEO/301 riski yok.**
- Motor iki yerde: tema `functions.php` (`tls_cat_mains`, `tls_cat_norm`,
  `tls_cat_explicit_map`, `tls_cat_guess_main($name,$slug)`, `tls_cat_main_of($term)`)
  ve panel `api/category-organize.php` (`co_*`). **Panel öneri için temanın
  `tls_cat_guess_main`'ini kullanır** (wp-load yüklü) — drift bitti.
  Eşleşmeyen → `_other` = "Themes & Movements".
- **ÖNEMLİ GOTCHA:** Panelden API'ye **`items` diye JSON dizisi POST ETME** — bir
  güvenlik filtresi o yükü düşürüyor (`$_POST['items']` boş geliyor). "Açıklama 0" ve
  "AI 0 öneri" hatalarının kök nedeni buydu. **Çözüm deseni: taramayı SUNUCUDA yap**
  (server kategorileri kendi bulsun), istemci yalnız tetiklesin.
  `desc_fill` ve `ai_suggest` böyle yazıldı.
- **Üretim hattı (batch):** `thetelos-panel/api/batch-worker.php` merdiveni —
  kaynaklı içeriği DeepSeek yazar; olmazsa Claude kendi bilgisinden UZUN özet
  (Claude tam kitabı OKUMAZ); o da yoksa DeepSeek bilgi-metni (Claude denetler).
  İlgili: `_proto.php`, `_info.php` (`tls_info_generate`/`tls_referee`),
  `_anthropic.php`, `_verify.php`. DeepSeek pik saatleri `bw_peak_now` ile atlanır.

## Anahtar dosyalar
- `page-categories.php` — kategoriler sayfası (başlık+arama+SORT tek panel, 15 konu
  accordion, açık satır vurgulu; chip satırı kaldırıldı; flicker çözüldü).
- `archive.php` — kategori/etiket arşivi (Works/Authors + A-Z filtre, "X / toplam" sayaç).
- `template-listauthors.php` — yazar arşivi anlık arama.
- `functions.php` — `thetelos_smart_search`, arama filtreleri, `tls_cat_*` motoru.
- `thetelos-panel/category-organize.php` (UI) + `thetelos-panel/api/category-organize.php`
  (actions: `list`/`apply`/`mains`/`ai_suggest`/`desc_scan`/`desc_fill`).

## Şu ana kadar biten (hepsi push'lu)
- Yazar & site arama düzeltmeleri; kenar çubuğu filtreleri.
- Kategoriler sayfası UX (mockup'a göre; chip satırı kaldırıldı; flicker düzeldi; açık satır vurgusu).
- 14 ana kategori gruplaması (panel + tema, URL güvenli); panel öneri = tema motoru;
  anahtar kelime aileleri genişletildi (marxism, semiotics, sufism, nihilism, stoicism… vb.).
- **AI ile Öner** sunucu-taramalı + önerileri **anında DB'ye kaydeder**.
- Kategori açıklamaları `desc_fill` sunucu-taramalı, dolduruldu.
- `archive.php` sayaç "X / toplam" (harf/arama filtresi açıkken).

## Açık / ertelenen işler
1. **İç linkleme (SEO):** `the_content` filtresiyle özet içinde geçen kitap/yazar
   adlarını ilgili özet/yazar arşivine linkleme.
2. **Niş/çöp kategori birleştirme:** "Kategori Temizle" (`category-cleanup.php`) ile
   gerçek birleştirme + 301.
3. Son deploylar (archive sayaç, AI auto-save) canlıda kullanıcıyla doğrulanacak.
