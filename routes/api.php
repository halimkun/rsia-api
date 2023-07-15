<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\api\dokter\DokterController;
use App\Http\Controllers\api\dokter\PasienController;
use App\Http\Controllers\api\dokter\KunjunganController;
use App\Http\Controllers\api\dokter\PasienRalanController;
use App\Http\Controllers\api\dokter\PasienRanapController;
use App\Http\Controllers\api\dokter\JadwalOperasiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/', function () {
    return isOk('API is running');
});

// Auth without middleware
Route::prefix('auth')->group(function ($router) {
    Route::post('login', [AuthController::class, 'login']);
});

// Auth
Route::middleware('api')->prefix('auth')->group(function ($router) {
    Route::post('validate', [AuthController::class, 'validateToken']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('me', [AuthController::class, 'me']);
});

// Dokter Endpoints
Route::middleware('api')->prefix('dokter')->group(function ($router) {
    Route::get('spesialis', [DokterController::class, 'spesialis']);
    Route::get('/', [DokterController::class, 'index']);

    // Pasien Rawat Inap
    Route::get('pasien/ranap/{tahun}/{bulan}/{tanggal}', [PasienRanapController::class, 'byDate']);
    Route::get('pasien/ranap/{tahun}/{bulan}', [PasienRanapController::class, 'byDate']);
    Route::get('pasien/ranap/{tahun}', [PasienRanapController::class, 'byDate']);
    Route::get('pasien/ranap/now', [PasienRanapController::class, 'now']);
    Route::get('pasien/ranap', [PasienRanapController::class, 'index']);

    // Pasien Rawat Jalan
    Route::get('pasien/ralan/{tahun}/{bulan}/{tanggal}', [PasienRalanController::class, 'byDate']);
    Route::get('pasien/ralan/{tahun}/{bulan}', [PasienRalanController::class, 'byDate']);
    Route::get('pasien/ralan/{tahun}', [PasienRalanController::class, 'byDate']);
    Route::get('pasien/ralan/now', [PasienRalanController::class, 'now']);
    Route::get('pasien/ralan', [PasienRalanController::class, 'index']);

    // Semua Pasien (termasuk rawat inap dan rawat jalan)
    Route::get('pasien/{tahun}/{bulan}/{tanggal}', [PasienController::class, 'byDate']);
    Route::get('pasien/{tahun}/{bulan}', [PasienController::class, 'byDate']);
    Route::get('pasien/{tahun}', [PasienController::class, 'byDate']);
    Route::get('pasien/now', [PasienController::class, 'now']);
    Route::get('pasien', [PasienController::class, 'index']);

    // Pemeriksaan Pasien
    Route::post('pasien/pemeriksaan', [PasienController::class, 'pemeriksaan']);

    // Jadwal Operasi Dokter
    Route::get('jadwal/operasi/{tahun}/{bulan}/{tanggal}', [JadwalOperasiController::class, 'byDate']);
    Route::get('jadwal/operasi/{tahun}/{bulan}', [JadwalOperasiController::class, 'byDate']);
    Route::get('jadwal/operasi/{tahun}', [JadwalOperasiController::class, 'byDate']);
    Route::get('jadwal/operasi/now', [JadwalOperasiController::class, 'now']);
    Route::get('jadwal/operasi', [JadwalOperasiController::class, 'index']);

    // Kunjungan Dokter
    Route::get('kunjungan/{tahun}/{bulan}/{tanggal}', [KunjunganController::class, 'byDate']);
    Route::get('kunjungan/{tahun}/{bulan}', [KunjunganController::class, 'byDate']);
    Route::get('kunjungan/{tahun}', [KunjunganController::class, 'byDate']);
    Route::get('kunjungan/now', [KunjunganController::class, 'now']);
    Route::get('kunjungan', [KunjunganController::class, 'index']);
});
