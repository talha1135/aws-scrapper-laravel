<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileUploadController;

Route::get('/', [FileUploadController::class, 'index'])->name('file.upload');
Route::post('/upload', [FileUploadController::class, 'upload'])->name('file.upload.post');
Route::get('/file/download/{fileName}', [FileUploadController::class, 'download'])->name('file.download');