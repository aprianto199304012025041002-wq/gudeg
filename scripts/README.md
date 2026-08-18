# Scripts

## sync_sso_public_key.py

Sync `SSO_PUBLIC_KEY` GitHub Secret ke semua repo klien (PASAR, KOMENK,
dan KASPER kalau sudah aktif) sekaligus. Dipakai saat rotate RSA key
GUDEG supaya tidak ada app klien yang lupa diupdate.

### Setup

```bash
pip install -r requirements.txt
```

### Pemakaian

```bash
export GITHUB_PAT=github_pat_xxx   # butuh izin secrets:write ke tiap repo target

python3 sync_sso_public_key.py keys/public.pem
# atau
python3 sync_sso_public_key.py --inline "-----BEGIN PUBLIC KEY-----...-----END PUBLIC KEY-----"
```

Repo target didefinisikan di `TARGET_REPOS` dalam skrip. Tambah `kasper`
ke daftar itu begitu KASPER punya SSO secrets di GitHub.

### Prosedur rotate key lengkap

1. Generate key baru:
   ```bash
   openssl genrsa -out keys/private.pem 2048
   openssl rsa -in keys/private.pem -pubout -out keys/public.pem
   ```
2. Update secret `RSA_PRIVATE_KEY` dan `RSA_PUBLIC_KEY` di repo **gudeg**
   (manual, lewat GitHub UI — private key tidak pernah lewat skrip ini)
3. Jalankan skrip ini untuk sync `SSO_PUBLIC_KEY` ke semua repo klien
4. Invalidate semua session aktif di Supabase:
   ```sql
   DELETE FROM sessions;
   ```
5. Lakukan di luar jam kerja — semua user yang sedang login akan ter-logout
