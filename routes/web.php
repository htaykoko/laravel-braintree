<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/welcome', function () {
    return view('welcome');
})->name("welcome");

Route::get('/', [PaymentController::class, 'payForm']);
Route::post('/payments/paypal', [PaymentController::class, 'payWithPayPal']);
Route::post('/payments/braintree', [PaymentController::class, 'payWithBraintree']);
