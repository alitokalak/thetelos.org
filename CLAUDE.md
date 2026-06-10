# thetelos.org — Proje Kuralları

WordPress teması: kitap analizleri sitesi. Özellikler: kitap kapağı otomatik çekme (`inc/book-cover-fetcher.php`), analiz CPT (`inc/analysis-cpt.php`).

## Kitap listesi (CSV) üretim kuralları — HER ZAMAN uygula

Bir yazarın eser listesi üretilirken:

1. **Tekrar yasak:** Aynı eser farklı başlıkla (İngilizce/Latince/alternatif ad) iki kez listelenemez. Üretimden sonra varyant bazında tekilleştir.
2. **Şüpheli/sahte atıf yasak:** Yalnızca güvenle otantik eserler listelenir. Dubia (tartışmalı) ve spuria (sahte) atıflar dahil edilmez.
3. **Kaynaktan doğrula:** Otantiklik hafızadan değil, standart akademik katalogdan doğrulanır (ör. Aquinas için Torrell / Leonine kataloğu; diğer yazarlar için muadili).
4. **Çıktı formatı:** `Kitap Adı,Yazar Adı,Yıl` başlıklı CSV; başlıklar tırnak içinde.
5. **Teslimden önce kontrol:** `sort | uniq -d` ile tekrar kontrolü yap; satır sayısını raporla.

Referans temiz liste: `aquinas_temiz_51.csv` (51 doğrulanmış eser).
