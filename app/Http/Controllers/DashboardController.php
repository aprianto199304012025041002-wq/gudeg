<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {

        // Ambil tanggal query terakhir

        $lastQuery = DB::connection('supabase')
            ->table('sims_trend')
            ->max(DB::raw('"TGL_QUERY"'));

        // Ambil total data berdasarkan tanggal terbaru

        $totalTrend = DB::connection('supabase')
            ->table('sims_trend')
            ->selectRaw('COUNT("TGL_QUERY") as total')
            ->groupBy(DB::raw('"TGL_QUERY"'))
            ->orderByRaw('"TGL_QUERY" DESC')
            ->first();

        return view('dashboard', [

            'lastQuery' => $lastQuery,

            'totalTrend' => $totalTrend
                ? $totalTrend->total
                : 0

        ]);
    }
}
