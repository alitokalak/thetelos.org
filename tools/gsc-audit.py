#!/usr/bin/env python3
"""Search Console 'Tarandı - dizine eklenmedi' örneklemindeki kitap sayfalarının
içeriğini ölçer: kelime sayısı, yer-tutucu mu, kısa not mu, tam özet mi."""
import re, sys, time, urllib.request, html, collections
urls = [u.strip() for u in open(sys.argv[1]) if u.strip()]
buckets = collections.Counter(); rows = []
for u in urls:
    try:
        req = urllib.request.Request(u, headers={'User-Agent': 'Mozilla/5.0 (thetelos-audit)'})
        h = urllib.request.urlopen(req, timeout=30).read().decode('utf-8', 'ignore')
    except Exception as e:
        buckets['hata'] += 1; rows.append((u, -1, 'hata ' + str(e)[:40])); time.sleep(1); continue
    m = re.search(r'<article class="tls-article-content[^"]*">(.*?)</article>', h, re.S)
    body = m.group(1) if m else h
    body = re.sub(r'<(script|style|nav|aside)[^>]*>.*?</\1>', ' ', body, flags=re.S)
    txt = html.unescape(re.sub(r'<[^>]+>', ' ', body))
    w = len(txt.split())
    robots = re.search(r'<meta name="robots" content="([^"]+)"', h)
    if 'is being prepared and will be published here soon' in h: k = 'yer-tutucu'
    elif w < 600: k = 'kisa (<600)'
    elif w < 1500: k = 'orta (600-1500)'
    else: k = 'uzun (1500+)'
    buckets[k] += 1; rows.append((u, w, k + ' | robots=' + (robots.group(1) if robots else '-')))
    time.sleep(1.2)
for r in rows: print(f'{r[1]:6d}  {r[2]:40s} {r[0]}')
print('\nOZET:', dict(buckets))
