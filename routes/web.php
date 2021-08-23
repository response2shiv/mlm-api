<?php

use Mpociot\ApiDoc\ApiDoc;

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

Route::get('/', function () {
    return view('welcome');
});

Route::get('/v1/docs', function () {
    //echo "test";
    return view('apidoc.index');
});

# FAKE WORLD SERIES DATA
Route::get('/worldseries', 'Affiliates\WorldSeriesController@formData')->name('worldseries');
Route::post('/worldseries-data', 'Affiliates\WorldSeriesController@fakeData')->name('worldseries-data');

ApiDoc::routes();

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');
