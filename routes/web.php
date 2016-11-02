<?php

Route::group(['namespace' => '\Konsulting\Butler\Controllers'], function () {
    Route::get('/auth/provider/confirm/{token}', 'AuthController@confirm')->name('butler.confirm');
    Route::get('/auth/provider/{provider}', 'AuthController@redirect')->name('butler.redirect');
    Route::get('/auth/provider/{provider}/callback', 'AuthController@callback')->name('butler.callback');
});
