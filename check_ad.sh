#!/usr/bin/env bash
# ============================================================
# AD (172.28.2.178) LDAPS/389 real-vaqtda kuzatish skripti
#
# Ishlatish:
#   ./check_ad.sh            — har 3 soniyada yangilanib turadi (kuzatish rejimi)
#   ./check_ad.sh --once     — bitta tekshirish
#   ./check_ad.sh --loop     — qayta ishga tushirish bilan kuzatish (holat o'zgarsa signal)
#   watch -c -n 2 ./check_ad.sh --once   — watch bilan birga
# ============================================================

set -u

# ── Sozlamalar: .env dan o'qiymiz (yo'q bo'lsa default) ──────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.env"

LDAP_HOST="172.28.2.178"
LDAP_PORT="636"
LDAP_BASE_DN="DC=adatum,DC=com"
LDAP_USER="administrator@adatum.com"
LDAP_PASS=""

if [[ -f "$ENV_FILE" ]]; then
    while IFS='=' read -r k v; do
        [[ "$k" =~ ^LDAP_(HOST|PORT|BASE_DN|SERVICE_USER|SERVICE_PASS)$ ]] || continue
        v="${v%\"}"; v="${v#\"}"
        case "$k" in
            LDAP_HOST)         LDAP_HOST="$v" ;;
            LDAP_PORT)         LDAP_PORT="$v" ;;
            LDAP_BASE_DN)      LDAP_BASE_DN="$v" ;;
            LDAP_SERVICE_USER) LDAP_USER="$v" ;;
            LDAP_SERVICE_PASS) LDAP_PASS="$v" ;;
        esac
    done < "$ENV_FILE"
fi

# ── Ranglar ───────────────────────────────────────────────────────────
GREEN=$'\033[32m'; RED=$'\033[31m'; YELLOW=$'\033[33m'; BOLD=$'\033[1m'
NC=$'\033[0m'

# ── Tekshirishlar ─────────────────────────────────────────────────────
check_ldaps() {
    local out
    out=$(printf 'Q' | timeout 8 openssl s_client -connect "$LDAP_HOST:636" -servername adatum.com 2>&1)

    if echo "$out" | grep -q "no peer certificate"; then
        echo "$RED❌ LDAPS(636): TLS boshlanmayapti — server sertifikat jo'natmayapti (xizmat yoq emas)$NC"
        return 1
    fi
    if echo "$out" | grep -qE "subject=|Verify return code: 0"; then
        local subj
        subj=$(echo "$out" | grep -m1 "subject=" | sed 's/^.*subject=//' | cut -c1-60)
        echo "$GREEN✅ LDAPS(636): TLS ISHLADI — sertifikat: $subj$NC"
        return 0
    fi
    if echo "$out" | grep -q "errno="; then
        echo "$RED❌ LDAPS(636): ulanish uzildi (connection reset)$NC"
        return 1
    fi
    echo "$YELLOW⚠️  LDAPS(636): noaniq javob$NC"
    return 1
}

check_389() {
    local out
    out=$(timeout 8 ldapsearch -x -H "ldap://$LDAP_HOST:389" \
        -D "$LDAP_USER" -w "$LDAP_PASS" -b "$LDAP_BASE_DN" -s base "(objectClass=*)" 1.1 2>&1)

    if echo "$out" | grep -q "result: 0"; then
        echo "$GREEN✅ LDAP(389): BIND ISHLADI — login o'tmoqda$NC"
        return 0
    fi
    if echo "$out" | grep -qi "Strong(er) authentication"; then
        echo "$YELLOW⚠️  LDAP(389): Server javob beradi, lekin signing talab qiladi (LDAPServerIntegrity=1 kerak)$NC"
        return 1
    fi
    if echo "$out" | grep -qi "Can't contact"; then
        echo "$RED❌ LDAP(389): serverga ulanish yo'q$NC"
        return 1
    fi
    if echo "$out" | grep -qi "Invalid credentials"; then
        echo "$RED❌ LDAP(389): parol/login noto'g'ri$NC"
        return 1
    fi
    echo "$YELLOW⚠️  LDAP(389): noaniq javob — $(echo "$out" | head -1)$NC"
    return 1
}

# ── Bitta tekshirish ──────────────────────────────────────────────────
run_once() {
    echo "${BOLD}═══ AD test: $LDAP_HOST ($(date +%H:%M:%S)) ═══${NC}"
    check_ldaps
    check_389
}

# ── Asosiy ────────────────────────────────────────────────────────────
case "${1:-}" in
    --once)
        run_once
        exit 0
        ;;
    --loop)
        prev=""
        while true; do
            clear
            out=$(run_once)
            echo "$out"
            state=$(echo "$out" | grep -c "✅")
            if [[ "$state" -gt 0 && "$prev" != "$state" && -n "$prev" ]]; then
                echo -e "\a"   # signal — holat o'zgardi
            fi
            prev="$state"
            sleep 3
        done
        ;;
    *)
        # Default: kuzatish rejimi
        clear
        run_once
        echo
        echo "${BOLD}Kuzatish rejimi: Ctrl+C chiqish. Ishlatish:${NC}"
        echo "   ./check_ad.sh --loop   # holat o'zgarsa signal beradi"
        echo "   watch -c -n 2 ./check_ad.sh --once"
        ;;
esac
