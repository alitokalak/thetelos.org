# Köytarla Teması — Kurulum

## Yükleme (ilk kez)

1. **Görünüm → Temalar → Yeni tema ekle → Tema yükle** → `koytarla.zip` → Şimdi kur → **Etkinleştir**.
2. **Görünüm → Düzenleyici** ile Site Editor'ü açın. Header'da menü boşsa gezinme bloğuna tıklayıp mevcut menüyü seçin ya da sayfaları ekleyin.
3. **Görünüm → Düzenleyici → Stiller** bölümünde renk ve fontların (Fraunces / Manrope) göründüğünü kontrol edin.
4. **Ayarlar → Genel → Site başlığı**: `Köytarla`. Logo gelince Site Editor'de header'daki logo bloğuna yüklenecek.

## Güncelleme

Aynı zip'i yeniden yüklediğinizde WordPress "Mevcut temayı yüklenen ile değiştir" seçeneğini sunar; onaylayın.

> Önemli: Site Editor'de şablon veya parça üzerinde **kaydettiğiniz** değişiklikler veritabanında tutulur ve temadaki dosyanın önüne geçer. Temadaki güncellemeyi görmek için Düzenleyici → Şablonlar/Desenler → ilgili öğe → ⋮ → **Özelleştirmeleri sıfırla**.

## Önbellek (Cloudflare / LiteSpeed)

Tema; sepet, ödeme ve hesap sayfalarında `no-store` başlıkları gönderir. Cloudflare'de HTML'i önbelleğe alan bir **Cache Rule** kurarsanız şu yolları mutlaka hariç tutun: `/sepet*`, `/odeme*`, `/hesabim*`, `wp-admin`, ve `wordpress_logged_in` / `woocommerce_items_in_cart` çerezleri olan istekler.

## Dosya yapısı

```
koytarla/
├── style.css            Tema başlığı
├── theme.json           Tasarım sistemi (renk, font, boşluk, buton, başlık)
├── functions.php        Tema desteği, stil/font yükleme, önbellek başlıkları
├── inc/woocommerce.php  İndirim rozeti (%30 İNDİRİM), ücretsiz kargo eşiği
├── assets/css/theme.css Odak halkası, 44px dokunma alanı, 16px form, WooCommerce ince ayar
├── assets/fonts/        Fraunces + Manrope (yerel woff2, OFL lisanslı)
├── parts/               header, header-checkout (sade), footer
└── templates/           index, page, page-wide, page-legal, single, search, 404, page-checkout
```

Ürün, mağaza, sepet şablonları şu an WooCommerce'in kendi blok şablonlarından geliyor (tema renk ve fontlarını alırlar). Özel ürün sayfası ve kardeş ürün seçici sonraki adımlarda eklenecek.
