# Panduan Rotate RSA Key GUDEG

Private key (`keys/private.pem`) pernah ter-commit ke git history meskipun
sudah ada di `.gitignore` sekarang. Key yang sudah masuk history dianggap
bocor — perlu di-rotate dan history-nya dibersihkan.

## Langkah-langkah

### 1. Generate key baru (jalankan di lokal)
```bash
cd /path/to/gudeg

# Generate RSA 2048-bit private key baru
openssl genrsa -out keys/private.pem 2048

# Ekstrak public key dari private key
openssl rsa -in keys/private.pem -pubout -out keys/public.pem

# Verifikasi
openssl rsa -in keys/private.pem -check -noout
# Output: RSA key ok
```

### 2. Ambil isi public key baru (untuk PASAR)
```bash
cat keys/public.pem
```
Salin output ini — akan dipakai di langkah 5.

### 3. Bersihkan git history (hapus keys/ dari semua commit lama)
```bash
# Install git-filter-repo jika belum ada
pip install git-filter-repo

# Hapus folder keys/ dari seluruh git history
# PERINGATAN: ini rewrite semua commit hash
git filter-repo --path keys/ --invert-paths --force

# Setelah filter-repo, re-add remote origin (filter-repo menghapusnya untuk keamanan)
git remote add origin https://github.com/aprianto199304012025041002-wq/gudeg.git
```

### 4. Commit key baru + force push
```bash
# Pastikan .gitignore sudah mengecualikan keys/*.pem (sudah ada, tidak perlu diubah)
# Verifikasi:
cat .gitignore | grep keys

# Key baru TIDAK perlu di-commit (sudah di .gitignore, deploy via FTP langsung ke server)
# Hanya push perubahan history yang sudah bersih:
git push origin master --force
```

### 5. Update public key di PASAR (config.local.php di Hostinger)
Di server PASAR, buka `config.local.php` dan update konstanta `SSO_PUBLIC_KEY`:
```php
define('SSO_PUBLIC_KEY', '-----BEGIN PUBLIC KEY-----
<isi dengan output keys/public.pem yang baru>
-----END PUBLIC KEY-----');
```

### 6. Deploy key baru ke server GUDEG
Upload `keys/private.pem` dan `keys/public.pem` yang baru ke server GUDEG
via FTP (Hostinger file manager atau FTP client), ke folder `keys/`.

### 7. Test SSO flow
- Buka PASAR → klik login → redirect ke GUDEG → login → callback
- Pastikan token baru (signed dengan key baru) diterima dengan benar

## Catatan
- Token RS256 yang sedang aktif (expired dalam 15 menit) akan invalid setelah key diganti.
  User yang sedang login tidak terdampak — hanya yang di tengah SSO flow saat rotate berlangsung.
- Lakukan rotate di luar jam kerja untuk meminimalkan dampak.
- Setelah force push, siapapun yang punya clone lama perlu `git fetch --all` dan reset.
