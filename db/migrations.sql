-- GUDEG — SQL migrations untuk dijalankan manual di Supabase SQL Editor
-- Jalankan sekali, urut dari atas ke bawah.

-- ── #94: Index untuk performa query sessions ─────────────────────────────────
-- check_session_timeout() dan change-password.php filter/sort berdasarkan
-- last_activity dan user_id. Tanpa index ini → full table scan setiap request.
CREATE INDEX IF NOT EXISTS idx_sessions_last_activity ON sessions(last_activity DESC);
CREATE INDEX IF NOT EXISTS idx_sessions_user_id       ON sessions(user_id);

-- ── Opsional: index untuk user_logs (dipakai is_new_ip dan log admin) ─────────
CREATE INDEX IF NOT EXISTS idx_user_logs_user_id      ON user_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_user_logs_activity     ON user_logs(activity);
CREATE INDEX IF NOT EXISTS idx_admin_logs_admin_id    ON admin_logs(admin_id);

-- ── #95: Tabel user_favorites untuk fitur pin/favorit aplikasi ───────────────
-- User bisa tandai aplikasi favorit (bintang), favorit tampil di section
-- terpisah paling atas dashboard. Berlaku untuk semua role.
-- Catatan: akses via service_role key (PHP backend) — RLS tidak diperlukan
-- karena filter user_id selalu disertakan di query PHP.
CREATE TABLE IF NOT EXISTS user_favorites (
    user_id        TEXT        NOT NULL,
    application_id TEXT        NOT NULL,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (user_id, application_id)
);

CREATE INDEX IF NOT EXISTS idx_user_favorites_user_id ON user_favorites(user_id);
