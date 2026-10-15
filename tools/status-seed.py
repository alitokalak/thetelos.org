#!/usr/bin/env python3
"""Analytics CSV'sini (sayfa linki, tıklama sayısı) inc/status-seed.php'ye çevirir (opcache'li dizi).

Kullanım: python3 tools/status-seed.py liste.csv
Anahtar = linkin son parçası (yazının slug'ı). Aynı slug birden çok satırda
geçerse (ör. /kitap/ ve /analysis/kitap/) sayılar toplanır.
"""
import csv, sys, urllib.parse, os

src = sys.argv[1]
out = os.path.join(os.path.dirname(__file__), '..', 'inc', 'status-seed.php')
seed = {}
with open(src, newline='', encoding='utf-8-sig') as f:
    for row in csv.reader(f):
        if len(row) < 2:
            continue
        path, num = row[0].strip(), row[1].strip().replace('.', '').replace(',', '')
        if not num.isdigit():
            continue  # başlık satırı vb.
        path = urllib.parse.urlparse(path).path if '://' in path else path
        parts = [x for x in path.strip('/').split('/') if x]
        # Yalnız kitap yazıları (/slug/) ve analizleri (/analysis/slug/);
        # etiket, yazar arşivi, wp-content vb. sayfalar atlanır.
        if not (len(parts) == 1 or (len(parts) == 2 and parts[0] == 'analysis')):
            continue
        slug = urllib.parse.unquote(parts[-1]).lower()
        if not slug:
            continue
        seed[slug] = seed.get(slug, 0) + int(num)
def php_str(x):
    return "'" + x.replace('\\', '\\\\').replace("'", "\\'") + "'"
with open(out, 'w', encoding='utf-8') as f:
    f.write("<?php\n// Otomatik üretildi: tools/status-seed.py. Elle düzenleme.\n"
            "// slug => Analytics'teki toplam buton tıklaması (ham, katsayısız).\nreturn [\n")
    for k, v in sorted(seed.items()):
        f.write(f"{php_str(k)}=>{v},\n")
    f.write("];\n")
print(f'{len(seed)} kitap yazıldı -> {os.path.normpath(out)}')
