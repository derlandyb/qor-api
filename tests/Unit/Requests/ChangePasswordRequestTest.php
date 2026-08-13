<?php

use App\Http\Requests\Admin\ChangePasswordRequest;
use Illuminate\Support\Facades\Validator;

it('given change-password validation then the new password must satisfy the same strength rule as registration', function () {
    $rules = (new ChangePasswordRequest)->rules();

    $tooWeak = Validator::make([
        'current_password' => 'whatever',
        'password' => 'alllettersnodigits',
        'password_confirmation' => 'alllettersnodigits',
    ], $rules);

    $tooShort = Validator::make([
        'current_password' => 'whatever',
        'password' => 'Ab1',
        'password_confirmation' => 'Ab1',
    ], $rules);

    $strong = Validator::make([
        'current_password' => 'whatever',
        'password' => 'ValidPass1',
        'password_confirmation' => 'ValidPass1',
    ], $rules);

    expect($tooWeak->fails())->toBeTrue()
        ->and($tooWeak->errors()->has('password'))->toBeTrue()
        ->and($tooShort->fails())->toBeTrue()
        ->and($strong->fails())->toBeFalse();
});
