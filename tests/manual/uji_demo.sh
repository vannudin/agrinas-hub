#!/usr/bin/env bash
# Uji cepat akun demo: masuk dengan 8 peran, lalu pastikan beranda masing-masing
# terbuka (200) tanpa galat. Aplikasi harus sudah berjalan.
#
#   php artisan serve --port=8000 &
#   bash tests/manual/uji_demo.sh http://127.0.0.1:8000
#
# Keluar dengan kode 1 bila ada peran yang gagal masuk atau berandanya galat.
set -u

B=${1:-http://127.0.0.1:8000}
SANDI='Demo#Koperasi2026'
GAGAL=0

# Peran → beranda yang diharapkan
harap="/admin /admin /kerja /admin /keuangan /admin /admin /mitra"
i=0
for peran in agrinas pic manajer hr keuangan tni dinas vendor; do
  i=$((i + 1))
  tujuan=$(printf '%s' "$harap" | cut -d' ' -f"$i")
  jar=$(mktemp)
  token=$(curl -s -c "$jar" "$B/masuk" | grep -o 'name="_token" value="[^"]*"' | head -1 | cut -d'"' -f4)

  curl -s -b "$jar" -c "$jar" -D "$jar.h" -o /dev/null \
    --data-urlencode "_token=$token" \
    --data-urlencode "email=demo.$peran@contoh.test" \
    --data-urlencode "sandi=$SANDI" \
    "$B/masuk"

  lokasi=$(awk 'tolower($1)=="location:"{print $2}' "$jar.h" | tr -d '\r')
  kode=$(curl -s -b "$jar" -o /dev/null -w '%{http_code}' "${lokasi:-$B$tujuan}")

  if [ "$lokasi" != "$B$tujuan" ] || [ "$kode" != "200" ]; then
    printf 'GAGAL  %-9s tujuan=%s kode=%s\n' "$peran" "${lokasi:-tidak ada}" "$kode"
    GAGAL=1
  else
    printf 'lulus  %-9s %s\n' "$peran" "$tujuan"
  fi
  rm -f "$jar" "$jar.h"
done

if [ "$GAGAL" -ne 0 ]; then
  echo 'ada peran yang gagal — periksa storage/logs/laravel.log'
fi
exit "$GAGAL"
