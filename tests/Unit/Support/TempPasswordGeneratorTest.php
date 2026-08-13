<?php

use App\Http\Requests\RegisterRequest;
use App\Support\TempPasswordGenerator;
use Illuminate\Support\Facades\Validator;

it('given a generated temp password then it satisfies the same strength rule registration uses', function () {
    $password = TempPasswordGenerator::generate();

    $validator = Validator::make(
        ['password' => $password],
        ['password' => RegisterRequest::passwordStrengthRule()]
    );

    expect($validator->passes())->toBeTrue()
        ->and(strlen($password))->toBeGreaterThanOrEqual(8);
});
