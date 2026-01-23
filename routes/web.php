<?php

use Illuminate\Support\Facades\Route;
// routes/web.php atau routes/api.php
use Illuminate\Support\Facades\Log;


Route::get('/', function () {
    return view('welcome');
});

