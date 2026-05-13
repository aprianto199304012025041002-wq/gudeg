<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\SSOController;

/*
|--------------------------------------------------------------------------
| GUEST ROUTES (Belum Login)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::view('/', 'welcome')->name('login');
    Route::get('/login', fn() => redirect('/'))->name('login.redirect');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::view('/register', 'register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1');
});

/*
|--------------------------------------------------------------------------
| PUBLIC SSO VALIDATION (Server-to-Server)
|--------------------------------------------------------------------------
*/
Route::get('/sso/validate', [SSOController::class, 'validateToken']);

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES (Sudah Login)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'password.expire'])->group(function () {
    Route::get('/dashboard', function () {
        if (strtolower(Auth::user()->email) === 'admin@dummy.com') {
            $applications = DB::table('sso_applications')->get();
        } else {
            $applications = DB::table('sso_applications')
                ->join('user_applications', 'sso_applications.id', '=', 'user_applications.application_id')
                ->where('user_applications.user_id', Auth::id())
                ->select('sso_applications.*')
                ->get();
        }
        return view('dashboard', compact('applications'));
    })->name('dashboard');

    Route::get('/sso/authorize', [SSOController::class, 'authorizeSSO']);
});

Route::middleware('auth')->group(function () {
    Route::get('/settings', [AdminController::class, 'index'])->name('settings');
    Route::post('/settings/password', [AuthController::class, 'updatePassword'])->middleware('throttle:3,1');
    Route::post('/settings/sessions/destroy', [AuthController::class, 'destroySessions'])->name('sessions.destroy');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout.get');
    Route::get('/sso/me', [SSOController::class, 'me']);
});

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES (Proteksi Ekstra Middleware Admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])->group(function () {

    Route::prefix('settings/admin')->group(function () {
        Route::post('/reset-password', [AdminController::class, 'resetPassword']);
        Route::post('/update-role', [AdminController::class, 'updateRole']);
        Route::post('/toggle-active', [AdminController::class, 'toggleActive']);
        Route::post('/delete-user', [AdminController::class, 'destroy']);
        Route::post('/toggle-maintenance', [AdminController::class, 'toggleMaintenance']);

        Route::get('/user-apps/{id}', [AdminController::class, 'getUserApps']);
        Route::post('/user-apps', [AdminController::class, 'syncUserApps']);
    });

    Route::prefix('apps')->group(function () {
        Route::get('/', [AdminController::class, 'appsIndex']);
        Route::post('/store', [AdminController::class, 'appsStore']);
        Route::post('/delete', [AdminController::class, 'appsDestroy']);
        Route::post('/ping', [AdminController::class, 'appsPing']);
        Route::post('/toggle', [AdminController::class, 'appsToggle']);
    });
});

/*
|--------------------------------------------------------------------------
| SIMULASI SSO CLIENT (Untuk Uji Coba Lokal)
|--------------------------------------------------------------------------
*/
Route::get('/simulasi-sso', function () {
    return '
        <div style="font-family:sans-serif;text-align:center;margin-top:50px;">
            <h2>Simulasi Aplikasi Client SSO</h2>
            <p>Klik tombol di bawah untuk login via GUDEG JOGJA.</p><br>
            <a href="/simulasi-sso/login" style="padding:10px 20px;background:#007acc;color:white;text-decoration:none;border-radius:5px;font-weight:bold;">
                Login via GUDEG JOGJA
            </a>
        </div>
    ';
});

Route::get('/simulasi-sso/login', function () {
    return redirect(url('/sso/authorize') . '?redirect=' . urlencode(url('/simulasi-sso/callback')));
});

Route::get('/simulasi-sso/callback', function (Request $request) {
    $token = $request->query('token');
    if (!$token) return 'Gagal: Token SSO tidak ditemukan.';

    $response = Http::get(url('/sso/validate'), ['token' => $token]);

    if ($response->successful() && $response->json('success')) {
        $userData = $response->json('user');
        return '
            <div style="font-family:sans-serif;text-align:center;margin-top:50px;">
                <h1 style="color:green;">🎉 SSO BERHASIL!</h1>
                <div style="display:inline-block;text-align:left;background:#f4f4f4;color:#333;padding:20px;border-radius:8px;margin-top:20px;">
                    <p><b>Nama:</b> ' . $userData['name'] . '</p>
                    <p><b>Email:</b> ' . $userData['email'] . '</p>
                    <p><b>Role:</b> ' . $userData['role'] . '</p>
                </div>
            </div>
        ';
    }
    return 'Gagal: Token ditolak. Response: ' . $response->body();
});
