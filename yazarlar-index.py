"""
yazarlar-index.py
Firebase'de /yazarlar/{yazar_adi}/{key}: eser_adi yapısı oluşturur.
Bu yapı sayesinde index gerekmez, yazar adına göre direkt lookup mümkün olur.

Kullanım: python3 yazarlar-index.py
Kaldığı yerden devam eder (son_satir_yazarlar.log)
"""

import json, os, re, urllib.request, urllib.error

YAZAR_DOSYASI  = "yazarlar.txt"
ESER_DOSYASI   = "eserler.txt"
FIREBASE_URL   = "https://thetelos-db-default-rtdb.europe-west1.firebasedatabase.app"
LOG_DOSYASI    = "son_satir_yazarlar.log"
PAKET_BOYUTU   = 3000   # Firebase multi-path PATCH başına max kayıt

# Firebase key olarak kullanılamayan karakterleri temizle
YASAK = str.maketrans({'.':'_', '#':'_', '$':'_', '[':'_', ']':'_', '/':'_'})

def norm_yazar(ad):
    return ad.strip().lower().translate(YASAK)

def firebase_patch(veri):
    body = json.dumps(veri).encode("utf-8")
    req = urllib.request.Request(
        f"{FIREBASE_URL}/.json",
        data=body,
        method="PATCH",
        headers={"Content-Type": "application/json"}
    )
    with urllib.request.urlopen(req, timeout=60) as r:
        return r.status

# ── 1. Yazarları hafızaya al ─────────────────────────────────────────
print("1. Yazarlar hafızaya alınıyor...")
yazar_haritasi = {}
with open(YAZAR_DOSYASI, "r", encoding="utf-8") as f:
    for satir in f:
        try:
            parcalar = satir.split("\t")
            if len(parcalar) >= 5:
                veri = json.loads(parcalar[4].strip())
                y_id  = veri.get("key")
                y_adi = veri.get("name")
                if y_id and y_adi:
                    yazar_haritasi[y_id] = y_adi
        except:
            continue
print(f"   {len(yazar_haritasi):,} yazar yüklendi.")

# ── 2. Log'dan kaldığı yeri oku ──────────────────────────────────────
baslangic = 0
if os.path.exists(LOG_DOSYASI):
    try:
        baslangic = int(open(LOG_DOSYASI).read().strip())
    except:
        baslangic = 0
print(f"2. Eserler işleniyor (satır {baslangic+1}'den başlıyor)...")

# ── 3. Eserler üzerinden geç, /yazarlar/ yaz ─────────────────────────
paket       = {}
satir_no    = 0
gonderi_say = 0

with open(ESER_DOSYASI, "r", encoding="utf-8") as f:
    for satir in f:
        satir_no += 1
        if satir_no <= baslangic:
            continue

        try:
            parcalar = satir.split("\t")
            if len(parcalar) < 5:
                continue
            veri = json.loads(parcalar[4].strip())

            eser_adi      = veri.get("title", "").strip()
            eser_yazarlari = veri.get("authors", [])
            if not eser_adi or not eser_yazarlari:
                continue

            yazar_link = eser_yazarlari[0].get("author", {}).get("key")
            yazar_adi  = yazar_haritasi.get(yazar_link)
            if not yazar_adi:
                continue

            norm = norm_yazar(yazar_adi)
            key  = f"k{satir_no}"
            # Multi-path key: "yazarlar/{norm}/{key}"
            paket[f"yazarlar/{norm}/{key}"] = eser_adi

            if len(paket) >= PAKET_BOYUTU:
                firebase_patch(paket)
                gonderi_say += len(paket)
                open(LOG_DOSYASI, "w").write(str(satir_no))
                print(f"   → {satir_no:,} satır işlendi ({gonderi_say:,} eser gönderildi)")
                paket.clear()

        except Exception as e:
            continue

# Son paketi gönder
if paket:
    firebase_patch(paket)
    gonderi_say += len(paket)
    open(LOG_DOSYASI, "w").write(str(satir_no))

print(f"\nBİTTİ! Toplam {satir_no:,} satır, {gonderi_say:,} eser Firebase'e yazıldı.")
print("Firebase'de /yazarlar/ node'u kullanıma hazır.")
