<?php

use App\Http\Resources\PromoterResource;
use App\Models\Promoter;
use Illuminate\Http\Request;

it('given a promoter when it is resolved through its resource then it exposes the documented fields', function () {
    $promoter = Promoter::factory()->create(['name' => 'Rock Produções']);

    $resolved = (new PromoterResource($promoter))->resolve(Request::create('/'));

    expect($resolved)->toMatchArray([
        'id' => (string) $promoter->id,
        'name' => 'Rock Produções',
        'verificationStatus' => $promoter->verification_status->value,
    ])->and($resolved)->toHaveKey('imageUrl');
});
