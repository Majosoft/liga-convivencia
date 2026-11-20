#!/usr/bin/env bash
# Ejecuta todas las pruebas del plugin (PHP y JavaScript). Sale con 1 si falla alguna.
#
#   bash tests/run-all.sh
#
# Requisitos: PHP 8.0+ con pdo_sqlite y Node 20+. Sin WordPress ni dependencias.
set -u
cd "$(dirname "$0")/.."

fallos=0
for prueba in tests/*-test.php; do
  echo "══ $prueba"
  php "$prueba" | tail -n 1
  [ "${PIPESTATUS[0]}" -eq 0 ] || { fallos=1; php "$prueba" | grep "✗"; }
done

echo "══ tests/js (Node)"
log="$(mktemp)"
if node --test tests/js/*.test.mjs > "$log" 2>&1; then
  grep -E "^ℹ (tests|pass|fail)" "$log"
else
  fallos=1; cat "$log"
fi
rm -f "$log"

echo
[ "$fallos" -eq 0 ] && echo "✔ Todas las pruebas pasan" || echo "✖ Hay pruebas que fallan"
exit "$fallos"
