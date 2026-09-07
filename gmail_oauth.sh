#!/bin/sh
# gmail_oauth.sh — build the Gmail OAuth consent URL and exchange the code,
# one prompt at a time. Run locally; secrets stay in the terminal.
# Usage: ./gmail_oauth.sh
set -eu

printf 'Google OAuth client ID: '
read -r CLIENT_ID
printf '\n1. Open this URL, approve as sending Gmail, copy full URL from localhost address bar:\n'
printf 'https://accounts.google.com/o/oauth2/v2/auth?client_id=%s&redirect_uri=http://localhost&response_type=code&scope=https://www.googleapis.com/auth/gmail.send&access_type=offline&prompt=consent\n' "$CLIENT_ID"

printf '\nPaste full localhost URL: '
read -r REDIRECT
CODE=$(printf '%s' "$REDIRECT" | sed -n 's/.*[?&]code=\([^&]*\).*/\1/p')
if [ -z "$CODE" ]; then CODE="$REDIRECT"; fi
printf 'Google OAuth client secret: '
read -r CLIENT_SECRET

RESP=$(curl -s -X POST https://oauth2.googleapis.com/token \
    -d code="$CODE" \
    -d client_id="$CLIENT_ID" \
    -d client_secret="$CLIENT_SECRET" \
    -d redirect_uri=http://localhost \
    -d grant_type=authorization_code)

TOKEN=$(printf '%s' "$RESP" | sed -n 's/.*"refresh_token"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p')
if [ -z "$TOKEN" ]; then
    printf '\nExchange failed. Google replied:\n%s\n' "$RESP"
    exit 1
fi
printf '\n2. Paste this into Render env as GMAIL_REFRESH_TOKEN:\n%s\n' "$TOKEN"
