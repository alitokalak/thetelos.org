#!/usr/bin/env bash
# 522 teşhisi: siteye hem Cloudflare üzerinden hem doğrudan sunucuya (origin IP) bağlanır.
# CF 522 iken origin de cevap vermiyorsa sunucu/ağ sorunu; origin cevap veriyorsa
# sunucunun güvenlik duvarı Cloudflare IP'lerini engelliyor demektir.
ORIGIN=95.173.165.196
ROUNDS=${ROUNDS:-50}
for i in $(seq 1 "$ROUNDS"); do
  t=$(date -u +%H:%M:%S)
  for d in thetelos.org koytarla.com; do
    cf=$(curl -s -o /dev/null -m 30 -w "%{http_code}/%{time_total}" "https://$d/?probe=$RANDOM")
    og=$(curl -sk -o /dev/null -m 30 -w "%{http_code}/c%{time_connect}/t%{time_total}" --resolve "$d:443:$ORIGIN" "https://$d/?probe=$RANDOM")
    echo "$t $d CF=$cf ORIGIN=$og"
  done
  ping -c1 -W3 $ORIGIN >/dev/null 2>&1 && echo "$t ping ok" || echo "$t ping YOK"
  sleep 30
done
