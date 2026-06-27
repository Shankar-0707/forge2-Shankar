<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature', 'Unit');

expect()->extend('toBeOne', function () {
    return $this->assertCount(1, $this->value);
});
