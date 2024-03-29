<?php

Route::group(['middleware' => ['web'], 'namespace' => '\\Konsulting\\Butler\\Controllers'], function () {
    Route::get('/auth/provider/confirm/{token}', 'AuthController@confirm')->name('butler.confirm');
    Route::get('/auth/provider/{provider}', 'AuthController@redirect')->name('butler.redirect');
    Route::match(['get', 'post'], '/auth/provider/{provider}/callback', 'AuthController@callback')->name('butler.callback');
});
