<?php

use Illuminate\Http\Request;

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

// adding routes for default files entities
Route::get('/api/files', '\Ordent\RamenFile\Controllers\FilesController@getCollection');
Route::get('/api/files/{id}', '\Ordent\RamenFile\Controllers\FilesController@getItem');
Route::post('/api/files', '\Ordent\RamenFile\Controllers\FilesController@postItem');
Route::post('/api/files/{id}', '\Ordent\RamenFile\Controllers\FilesController@putItem');
Route::put('/api/files/{id}', '\Ordent\RamenFile\Controllers\FilesController@putItem');
Route::delete('/api/files/{id}', '\Ordent\RamenFile\Controllers\FilesController@deleteItem');
Route::post('/api/files/{id}/delete', '\Ordent\RamenFile\Controllers\FilesController@deleteItem');

Route::post('/api/uploads', '\Ordent\RamenFile\Controllers\FilesController@modellessUpload');