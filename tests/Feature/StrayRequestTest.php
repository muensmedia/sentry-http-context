<?php

use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;

it('blocks requests that were not faked', function () {
    Http::get('https://example.com/not-faked');
})->throws(StrayRequestException::class);
