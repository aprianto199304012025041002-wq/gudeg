<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::middleware('auth')->prefix('sso')->group(function () {

    Route::get('/me', function () {

        return response()->json([

            'authenticated' => true,

            'user' => [

                'id' => Auth::user()->id,

                'name' => Auth::user()->name,

                'email' => Auth::user()->email,

                'role' => Auth::user()->role,

            ]

        ]);

    });

    Route::get('/check', function () {

        return response()->json([
            'authenticated' => Auth::check()
        ]);

    });

});
