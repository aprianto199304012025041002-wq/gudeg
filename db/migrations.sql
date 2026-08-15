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
