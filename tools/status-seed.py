#!/usr/bin/env python3
"""Analytics CSV'sini (sayfa linki, tıklama sayısı) inc/status-seed.json'a çevirir.

Kullanım: python3 tools/status-seed.py liste.csv
Anahtar = linkin son parçası (yazının slug'ı). Aynı slug birden çok satırda
geçerse (ör. /kitap/ ve /analysis/kitap/) sayılar toplanır.
"""
import csv, json, sys, urllib.parse, os

src = sys.argv[1]
out = os.path.join(os.path.dirname(__file__), '..', 'inc', 'status-seed.json')
seed = {}
with open(src, newline='', encoding='utf-8-sig') as f:
    for row in csv.reader(f):
        if len(row) < 2:
            continue
        path, num = row[0].strip(), row[1].strip().replace('.', '').replace(',', '')
        if not num.isdigit():
            continue  # başlık satırı vb.
        path = urllib.parse.urlparse(path).path if '://' in path else path
        slug = urllib.parse.unquote(path.strip('/').split('/')[-1]).lower()
        if not slug:
            continue
        seed[slug] = seed.get(slug, 0) + int(num)
with open(out, 'w', encoding='utf-8') as f:
    json.dump(dict(sorted(seed.items())), f, ensure_ascii=False, indent=0)
print(f'{len(seed)} kitap yazıldı -> {os.path.normpath(out)}')
