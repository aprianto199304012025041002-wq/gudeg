#!/usr/bin/env python3
"""
Sync SSO_PUBLIC_KEY GitHub Secret ke semua repo klien GUDEG sekaligus.

Kegunaan: saat rotate RSA key GUDEG, public key baru harus disebar ke
PASAR, KOMENK, dan KASPER (kalau sudah aktif) secara bersamaan. Skrip ini
menghindari human error "lupa update salah satu app" yang bisa bikin
login gagal massal.

Pemakaian:
    python3 sync_sso_public_key.py path/to/public.pem

    atau paste langsung isi PEM:
    python3 sync_sso_public_key.py --inline "-----BEGIN PUBLIC KEY-----..."

Env var yang dibutuhkan:
    GITHUB_PAT   - token dengan izin secrets:write ke tiap repo target
"""

import sys
import os
import argparse
import base64
import requests
from nacl import encoding, public

ORG = "aprianto199304012025041002-wq"

# Repo klien yang butuh SSO_PUBLIC_KEY. Tambah/kurangi di sini kalau ada
# aplikasi baru yang join SSO atau keluar.
TARGET_REPOS = ["pasar", "komenk"]
# KASPER belum ada SSO secrets di GitHub per catatan terakhir.
# Uncomment saat KASPER sudah disetup:
# TARGET_REPOS.append("kasper")


def encrypt_secret(public_key_b64: str, secret_value: str) -> str:
    """Enkripsi secret pakai libsodium sealed box, sesuai spec GitHub Actions Secrets API."""
    public_key = public.PublicKey(public_key_b64.encode("utf-8"), encoding.Base64Encoder())
    sealed_box = public.SealedBox(public_key)
    encrypted = sealed_box.encrypt(secret_value.encode("utf-8"))
    return base64.b64encode(encrypted).decode("utf-8")


def get_public_key(session: requests.Session, repo: str) -> dict:
    r = session.get(f"https://api.github.com/repos/{ORG}/{repo}/actions/secrets/public-key")
    r.raise_for_status()
    return r.json()


def set_secret(session: requests.Session, repo: str, secret_name: str, secret_value: str) -> int:
    key_data = get_public_key(session, repo)
    encrypted_value = encrypt_secret(key_data["key"], secret_value)
    r = session.put(
        f"https://api.github.com/repos/{ORG}/{repo}/actions/secrets/{secret_name}",
        json={"encrypted_value": encrypted_value, "key_id": key_data["key_id"]},
    )
    return r.status_code


def main():
    parser = argparse.ArgumentParser(description="Sync SSO_PUBLIC_KEY ke semua repo klien")
    parser.add_argument("pem_file", nargs="?", help="Path ke file public.pem")
    parser.add_argument("--inline", help="Isi PEM langsung sebagai string")
    parser.add_argument(
        "--repos",
        nargs="*",
        default=TARGET_REPOS,
        help=f"Override daftar repo target (default: {TARGET_REPOS})",
    )
    args = parser.parse_args()

    pat = os.environ.get("GITHUB_PAT")
    if not pat:
        print("ERROR: set env var GITHUB_PAT dulu.", file=sys.stderr)
        sys.exit(1)

    if args.inline:
        pem_content = args.inline
    elif args.pem_file:
        with open(args.pem_file, "r") as f:
            pem_content = f.read()
    else:
        print("ERROR: kasih path file .pem atau --inline '<isi pem>'", file=sys.stderr)
        sys.exit(1)

    if "BEGIN PUBLIC KEY" not in pem_content:
        print("ERROR: ini bukan public key PEM yang valid (harus ada 'BEGIN PUBLIC KEY').", file=sys.stderr)
        sys.exit(1)

    session = requests.Session()
    session.headers.update({
        "Authorization": f"token {pat}",
        "Accept": "application/vnd.github+json",
    })

    print(f"Target repo: {', '.join(args.repos)}")
    print("---")

    results = {}
    for repo in args.repos:
        try:
            status = set_secret(session, repo, "SSO_PUBLIC_KEY", pem_content)
            ok = status in (201, 204)
            results[repo] = ok
            print(f"{'OK  ' if ok else 'GAGAL'} {repo:10s} (HTTP {status})")
        except Exception as e:
            results[repo] = False
            print(f"GAGAL {repo:10s} error: {e}")

    print("---")
    failed = [r for r, ok in results.items() if not ok]
    if failed:
        print(f"Repo GAGAL disync: {', '.join(failed)} — cek manual!")
        sys.exit(1)
    else:
        print("Semua repo berhasil disync. Jangan lupa juga update RSA_PUBLIC_KEY di GUDEG sendiri kalau belum.")


if __name__ == "__main__":
    main()
