<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/privacy', function () {
    return view('privacy');
});
Route::get('/claim-reward', function () {
    return view('claim-reward');
});
Route::get('/referral-reward', function () {
    return view('referral-reward');
});
Route::get('/contents', function () {
    return view('contents');
});
Route::get('/devices', function () {
    return view('devices');
});
Route::get('/livesports', function () {
    return view('live-sport');
});

Route::get('/dashboard', function () {
    return view('welcome');
});
Route::get('/contents/new', function () {
    return view('new');
});
Route::get('/suggestions', function () {
    return view('suggestion');
});
Route::get('/sport', function () {
    return view('sports');
});
Route::get('/announces', function () {
    return view('announces');
});
Route::get('/contents/edit/{id}', function ($id) {
    return view('content-edit', ['id' => $id]);
});