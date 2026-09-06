<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('shop.index'));

Route::get('/shop', fn () => view('shop.index'))->name('shop.index');

Route::get('/admin/products', fn () => view('admin.products'))->name('admin.products');
