<?php

namespace Tests\Unit\Domain\Venue\UseCase;

use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\Shared\FileUploadPort;
use QOR\App\Domain\Shared\UploadableFile;
use QOR\App\Domain\Venue\UseCase\EditVenueProfile;
use QOR\App\Domain\Venue\Venue;
use QOR\App\Domain\Venue\VenueRepository;

class EditVenueProfileTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function existingVenue(): Venue
    {
        return new Venue(
            id: 1,
            venueAdminUserId: 42,
            name: 'Bar do Zé',
            description: 'Casa de shows no centro',
            address: 'Rua das Flores, 100',
            city: City::Vitoria,
            contactPhone: '27999999999',
            contactEmail: 'contato@bardoze.com',
            approvalStatus: ApprovalStatus::Approved,
            imageUrl: 'https://cdn/old.jpg',
        );
    }

    public function test_GIVEN_only_a_name_change_WHEN_editing_THEN_other_fields_remain_unchanged(): void
    {
        $venues = Mockery::mock(VenueRepository::class);
        $venues->shouldReceive('findById')->once()->with(1)->andReturn($this->existingVenue());
        $venues->shouldReceive('save')->once()->andReturnUsing(fn (Venue $venue) => $venue);

        $fileUpload = Mockery::mock(FileUploadPort::class);
        $fileUpload->shouldNotReceive('upload');

        $updated = (new EditVenueProfile($venues, $fileUpload))
            ->execute(venueId: 1, name: 'Bar do Zé Novo');

        $this->assertSame('Bar do Zé Novo', $updated->name);
        $this->assertSame('Casa de shows no centro', $updated->description);
        $this->assertSame('Rua das Flores, 100', $updated->address);
        $this->assertSame(City::Vitoria, $updated->city);
        $this->assertSame('27999999999', $updated->contactPhone);
        $this->assertSame('contato@bardoze.com', $updated->contactEmail);
        $this->assertSame(ApprovalStatus::Approved, $updated->approvalStatus);
        $this->assertSame('https://cdn/old.jpg', $updated->imageUrl);
    }

    public function test_GIVEN_an_image_upload_WHEN_editing_THEN_the_file_upload_port_is_used_with_the_venues_images_directory(): void
    {
        $venues = Mockery::mock(VenueRepository::class);
        $venues->shouldReceive('findById')->once()->with(1)->andReturn($this->existingVenue());
        $venues->shouldReceive('save')->once()->andReturnUsing(fn (Venue $venue) => $venue);

        $image = new UploadableFile('/tmp/img.jpg', 'img.jpg', 'image/jpeg', 1024, 500, 500);

        $fileUpload = Mockery::mock(FileUploadPort::class);
        $fileUpload->shouldReceive('upload')->once()->with($image, 'venues/images')->andReturn('https://cdn/new.jpg');

        $updated = (new EditVenueProfile($venues, $fileUpload))
            ->execute(venueId: 1, image: $image);

        $this->assertSame('https://cdn/new.jpg', $updated->imageUrl);
    }

    public function test_GIVEN_a_nonexistent_venue_id_WHEN_editing_THEN_it_throws(): void
    {
        $venues = Mockery::mock(VenueRepository::class);
        $venues->shouldReceive('findById')->once()->with(999)->andReturn(null);
        $venues->shouldNotReceive('save');

        $fileUpload = Mockery::mock(FileUploadPort::class);
        $fileUpload->shouldNotReceive('upload');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Venue não encontrada.');

        (new EditVenueProfile($venues, $fileUpload))->execute(venueId: 999);
    }

    public function test_GIVEN_the_approval_status_is_not_a_parameter_WHEN_editing_THEN_the_current_approval_status_is_preserved(): void
    {
        $venues = Mockery::mock(VenueRepository::class);
        $venues->shouldReceive('findById')->once()->with(1)->andReturn($this->existingVenue());
        $venues->shouldReceive('save')->once()->andReturnUsing(fn (Venue $venue) => $venue);

        $fileUpload = Mockery::mock(FileUploadPort::class);

        $updated = (new EditVenueProfile($venues, $fileUpload))
            ->execute(venueId: 1, contactPhone: '27988887777');

        $this->assertSame(ApprovalStatus::Approved, $updated->approvalStatus);
    }
}
