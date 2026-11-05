#!/usr/bin/env bash
# Fails when the repository holds something that looks like a secret or an internal address (WR-12): API keys and
# tokens in their known shapes, private IP addresses and the hosts of the real panel. Push protection on GitHub only
# knows token shapes; this also catches addresses.
set -euo pipefail
cd "$(dirname "$0")/.."

patterns=(
  'pnl_[A-Za-z0-9]{16,}'
  '\bsk-[A-Za-z0-9_-]{20,}'
  '\bsk-ant-[A-Za-z0-9_-]{10,}'
  'AKIA[0-9A-Z]{16}'
  'gh[pousr]_[A-Za-z0-9]{30,}'
  'github_pat_[A-Za-z0-9_]{30,}'
  'Bearer [A-Za-z0-9._-]{30,}'
  'CLOUDFLARE_API_TOKEN=[^[:space:]$]+'
  '-----BEGIN [A-Z ]*PRIVATE KEY-----'
  '\b(10\.[0-9]{1,3}|192\.168|172\.(1[6-9]|2[0-9]|3[01]))\.[0-9]{1,3}\.[0-9]{1,3}\b'
  '[A-Za-z0-9.-]+\.deckzwabbers\.nl'
)

found=0
for pattern in "${patterns[@]}"; do
  if hits=$(git ls-files -z | grep -zvE '^(tests/fixtures/|tests/secrets\.sh$)' | xargs -0 grep -nIE -e "$pattern" -- 2>/dev/null); then
    # Only where, never the value itself: CI logs are public.
    echo "$hits" | cut -d: -f1,2 | sed 's/^/  /'
    found=1
  fi
done

if [ "$found" = 1 ]; then
  echo 'FAIL the repository holds something that looks like a secret or an internal address (above)'
  exit 1
fi
echo 'PASS no secrets or internal addresses in the repository'
