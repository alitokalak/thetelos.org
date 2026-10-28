#!/usr/bin/env bash
# Sunucunun (Cloudflare'siz) SSL sertifikası tarayıcıda geçerli mi? (gri bulut öncesi kontrol)
for d in thetelos.org www.thetelos.org koytarla.com www.koytarla.com; do
  echo "== $d"
  echo | openssl s_client -connect 95.173.165.196:443 -servername "$d" 2>/dev/null | openssl x509 -noout -subject -issuer -enddate -ext subjectAltName 2>/dev/null | tr '\n' ' '; echo
  curl -s -o /dev/null -m 20 -w "dogrulamali-baglanti=%{http_code} ssl_verify=%{ssl_verify_result}\n" --resolve "$d:443:95.173.165.196" "https://$d/" || echo "dogrulamali-baglanti BASARISIZ"
done
