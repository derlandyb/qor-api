<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\EventStatus;
use App\Enums\VerificationApplicationStatus;
use App\Enums\VerificationStatus;
use App\Models\Artist;
use App\Models\Event;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use App\Models\VerificationApplication;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Populates the four PRD-scoped Grande Vitória cities (Vitória, Vila Velha, Serra, Cariacica)
 * with real venues and real artists/acts (researched from Sympla, Vibe Index, Agenda HZ, Tribuna
 * Online listings) so the seeded project reads like a real regional listings site instead of
 * generic Faker output. Promoter companies are plausible fictional entities — no real promoter
 * business names were reliably sourced. Idempotent by the first seeded event's title, mirroring
 * SuperAdminSeeder's skip-if-present convention.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Event::query()->where('title', 'Catedral - Rock & Hits')->exists()) {
            return;
        }

        $venues = $this->seedVenues();
        $promoters = $this->seedPromoters();
        $artists = $this->seedArtists();
        $this->seedEvents($venues, $promoters, $artists);
        $this->seedUsers($promoters, $venues);
    }

    /** @return array<string, Venue> keyed by a short handle used by seedEvents() */
    private function seedVenues(): array
    {
        $rows = [
            'patrick_ribeiro' => ['name' => 'Espaço Patrick Ribeiro', 'address' => 'Rod. Serafim Derenzi, Goiabeiras', 'city' => 'Vitória', 'latitude' => -20.2819, 'longitude' => -40.2967],
            'carlos_gomes' => ['name' => 'Theatro Carlos Gomes', 'address' => 'Praça Costa Pereira, Centro', 'city' => 'Vitória', 'latitude' => -20.3186, 'longitude' => -40.3378],
            'cais_das_artes' => ['name' => 'Cais das Artes', 'address' => 'Av. Rio Branco, Enseada do Suá', 'city' => 'Vitória', 'latitude' => -20.2968, 'longitude' => -40.2913],
            'casa_caipora' => ['name' => 'Casa Caipora', 'address' => 'Praia do Canto', 'city' => 'Vitória', 'latitude' => -20.2947, 'longitude' => -40.2887],
            'matrix' => ['name' => 'Matrix', 'address' => 'Rio Branco', 'city' => 'Vitória', 'latitude' => -20.3103, 'longitude' => -40.3211],
            'parque_prainha' => ['name' => 'Parque da Prainha', 'address' => 'Av. Champagnat, Praia da Costa', 'city' => 'Vila Velha', 'latitude' => -20.3417, 'longitude' => -40.2917],
            'dcastro' => ['name' => "Espaço D'Castro", 'address' => 'Itaparica', 'city' => 'Vila Velha', 'latitude' => -20.3372, 'longitude' => -40.2831],
            'barroco' => ['name' => 'Barroco Bar e Restaurante', 'address' => 'Centro', 'city' => 'Vila Velha', 'latitude' => -20.3297, 'longitude' => -40.2925],
            'larika' => ['name' => 'Larika Pub', 'address' => 'Praia da Costa', 'city' => 'Vila Velha', 'latitude' => -20.3389, 'longitude' => -40.2903],
            'hangar' => ['name' => 'Hangar Gastrobar', 'address' => 'Colina de Laranjeiras', 'city' => 'Serra', 'latitude' => -20.1219, 'longitude' => -40.3078],
            'wild_west' => ['name' => 'Cervejaria Wild West', 'address' => 'São Diogo I', 'city' => 'Serra', 'latitude' => -20.1289, 'longitude' => -40.3102],
            'moxuara' => ['name' => 'Shopping Moxuara', 'address' => 'Campo Grande', 'city' => 'Cariacica', 'latitude' => -20.2731, 'longitude' => -40.4181],
        ];

        $venues = [];
        foreach ($rows as $handle => $attrs) {
            $venues[$handle] = Venue::query()->create([
                ...$attrs,
                'description' => "Casa de shows em {$attrs['city']}.",
                'verification_status' => VerificationStatus::Verified,
                'social_links' => ['instagram' => '@'.str($attrs['name'])->slug('')],
            ]);
        }

        return $venues;
    }

    /** @return array<string, Promoter> keyed by a short handle */
    private function seedPromoters(): array
    {
        $rows = [
            'vibe_capixaba' => 'Vibe Capixaba Produções',
            'hz_entretenimento' => 'HZ Entretenimento',
            'litoral_shows' => 'Litoral Shows',
            'moqueca_live' => 'Moqueca Live Eventos',
        ];

        $promoters = [];
        foreach ($rows as $handle => $name) {
            $promoters[$handle] = Promoter::query()->create([
                'name' => $name,
                'description' => 'Produtora de eventos musicais na Grande Vitória.',
                'social_links' => ['instagram' => '@'.str($name)->slug('')],
                'contact_email' => str($name)->slug('.').'@example.com',
                'contact_phone' => '(27) 99999-0000',
                'verification_status' => VerificationStatus::Verified,
            ]);
        }

        return $promoters;
    }

    /** @return array<string, Artist> keyed by a short handle */
    private function seedArtists(): array
    {
        $rows = [
            'catedral' => ['name' => 'Catedral', 'genres' => ['rock', 'pop']],
            'traia_veia' => ['name' => 'Traia Véia', 'genres' => ['sertanejo']],
            'atom_pink_floyd' => ['name' => 'ATOM Pink Floyd', 'genres' => ['rock']],
            'tres_continentes' => ['name' => '3 Continentes', 'genres' => ['mpb', 'pop']],
            'amado_batista' => ['name' => 'Amado Batista', 'genres' => ['sertanejo', 'mpb']],
            'dalto' => ['name' => 'Dalto', 'genres' => ['mpb']],
            'biafra' => ['name' => 'Biafra', 'genres' => ['mpb']],
            'nico_resende' => ['name' => 'Nico Resende', 'genres' => ['mpb']],
            'pink_floyd_experience' => ['name' => 'Pink Floyd Experience', 'genres' => ['rock']],
            'elymar_santos' => ['name' => 'Elymar Santos', 'genres' => ['pagode', 'samba']],
            'black_circle' => ['name' => 'Black Circle', 'genres' => ['rock']],
            'eterea' => ['name' => 'Etérea', 'genres' => ['eletrônica']],
            'diogo_nogueira' => ['name' => 'Diogo Nogueira', 'genres' => ['samba', 'pagode']],
            'mel_lisboa' => ['name' => 'Mel Lisboa', 'genres' => ['rock', 'mpb']],
            'amazing_tenors' => ['name' => 'Amazing Tenors', 'genres' => ['mpb']],
            'guilherme_arantes' => ['name' => 'Guilherme Arantes', 'genres' => ['mpb', 'pop']],
            'michael_pipoquinha' => ['name' => 'Michael Pipoquinha', 'genres' => ['jazz']],
            'jean_felipe' => ['name' => 'Jean Felipe', 'genres' => ['sertanejo']],
            'raca_negra' => ['name' => 'Raça Negra', 'genres' => ['pagode', 'samba']],
            'jorge_aragao' => ['name' => 'Jorge Aragão', 'genres' => ['samba']],
            'durval_lelys' => ['name' => 'Durval Lelys', 'genres' => ['axé']],
            'renato_da_rocinha' => ['name' => 'Renato da Rocinha', 'genres' => ['samba']],
            'galocanto' => ['name' => 'Galocantô', 'genres' => ['samba', 'pagode']],
        ];

        $artists = [];
        foreach ($rows as $handle => $attrs) {
            $artists[$handle] = Artist::query()->create([
                'name' => $attrs['name'],
                'genres' => $attrs['genres'],
                'social_links' => ['instagram' => '@'.str($attrs['name'])->slug('')],
            ]);
        }

        return $artists;
    }

    /**
     * @param  array<string, Venue>  $venues
     * @param  array<string, Promoter>  $promoters
     * @param  array<string, Artist>  $artists
     */
    private function seedEvents(array $venues, array $promoters, array $artists): void
    {
        $now = Carbon::now(config('app.timezone'));
        $nextSaturday = $now->copy()->next(Carbon::SATURDAY);

        // [title, venue handle, artist handles, promoter handle, start, genres, price_min, price_max, is_free, status]
        $rows = [
            ['Catedral - Rock & Hits', 'patrick_ribeiro', ['catedral'], 'vibe_capixaba', $now->copy()->addHours(6), ['rock', 'pop'], 60, 150, false, EventStatus::Published],
            ['Traía Véia ao Vivo', 'patrick_ribeiro', ['traia_veia'], 'hz_entretenimento', $now->copy()->addDay()->setTime(21, 0), ['sertanejo'], 40, 100, false, EventStatus::Published],
            ['ATOM Pink Floyd - Ten Years in the Machine', 'carlos_gomes', ['atom_pink_floyd'], 'litoral_shows', $now->copy()->addDay()->setTime(20, 0), ['rock'], 50, 130, false, EventStatus::Published],
            ['3 Continentes - O Show', 'cais_das_artes', ['tres_continentes'], 'vibe_capixaba', $nextSaturday->copy()->setTime(19, 0), ['mpb', 'pop'], 45, 120, false, EventStatus::Published],
            ['Amado Batista - 50 Anos', 'patrick_ribeiro', ['amado_batista'], 'hz_entretenimento', $nextSaturday->copy()->setTime(21, 0), ['sertanejo', 'mpb'], 70, 180, false, EventStatus::Published],
            ['Clássicos Eternos - Dalto, Biafra e Nico Resende', 'carlos_gomes', ['dalto', 'biafra', 'nico_resende'], 'litoral_shows', $now->copy()->addDays(10)->setTime(20, 0), ['mpb'], 55, 140, false, EventStatus::Published],
            ['Pink Floyd Experience - In Concert', 'patrick_ribeiro', ['pink_floyd_experience'], 'vibe_capixaba', $now->copy()->addDays(10)->setTime(21, 30), ['rock'], 80, 200, false, EventStatus::Published],
            ['Elymar Santos ao Vivo', 'dcastro', ['elymar_santos'], 'moqueca_live', $now->copy()->addDays(15)->setTime(19, 0), ['pagode', 'samba'], 35, 90, false, EventStatus::Published],
            ['Pearl Jam Symphonic by Black Circle', 'patrick_ribeiro', ['black_circle'], 'vibe_capixaba', $now->copy()->addDays(20)->setTime(21, 0), ['rock'], 60, 160, false, EventStatus::Published],
            ['Etérea Ambient Vol. 2', 'casa_caipora', ['eterea'], 'litoral_shows', $now->copy()->addDays(5)->setTime(22, 0), ['eletrônica'], 30, 70, false, EventStatus::Published],
            ['Diogo Nogueira - Turnê 20 Anos', 'patrick_ribeiro', ['diogo_nogueira'], 'hz_entretenimento', $now->copy()->addDays(25)->setTime(21, 0), ['samba', 'pagode'], 90, 220, false, EventStatus::Published],
            ['Mel Lisboa Canta Rita Lee', 'patrick_ribeiro', ['mel_lisboa'], 'vibe_capixaba', $now->copy()->addDays(30)->setTime(20, 30), ['rock', 'mpb'], 55, 140, false, EventStatus::Published],
            ['Amazing Tenors | Sing Bocelli', 'carlos_gomes', ['amazing_tenors'], 'litoral_shows', $now->copy()->addDays(35)->setTime(20, 0), ['mpb'], 70, 190, false, EventStatus::Published],
            ['Guilherme Arantes - 50 Anos-Luz', 'patrick_ribeiro', ['guilherme_arantes'], 'vibe_capixaba', $now->copy()->addDays(7)->setTime(21, 0), ['mpb', 'pop'], 65, 130, false, EventStatus::Published],
            ['Michael Pipoquinha Jazz Concert', 'carlos_gomes', ['michael_pipoquinha'], 'litoral_shows', $now->copy()->addDays(3)->setTime(20, 0), ['jazz'], 0, 0, true, EventStatus::Published],
            ['Jean Felipe - Gravação de DVD', 'matrix', ['jean_felipe'], 'hz_entretenimento', $now->copy()->addDays(2)->setTime(19, 30), ['sertanejo'], 0, 0, true, EventStatus::Published],
            ['Aniversário de Vila Velha - Raça Negra', 'parque_prainha', ['raca_negra'], 'moqueca_live', $now->copy()->addDays(8)->setTime(19, 0), ['pagode', 'samba'], 0, 0, true, EventStatus::Published],
            ['Aniversário de Vila Velha - Jorge Aragão & Durval Lelys', 'parque_prainha', ['jorge_aragao', 'durval_lelys'], 'moqueca_live', $now->copy()->addDays(9)->setTime(19, 0), ['samba', 'axé'], 0, 0, true, EventStatus::Published],
            ['Samba que Eu Quero Ver', 'cais_das_artes', ['renato_da_rocinha', 'galocanto'], 'litoral_shows', $now->copy()->addDays(6)->setTime(19, 0), ['samba', 'pagode'], 0, 0, true, EventStatus::Published],
            ['Noite do Rock Capixaba', 'wild_west', ['catedral'], 'vibe_capixaba', $now->copy()->addDays(12)->setTime(21, 0), ['rock'], 25, 60, false, EventStatus::Published],
            ['Forró de Verão', 'hangar', ['jean_felipe'], 'hz_entretenimento', $now->copy()->addDays(14)->setTime(20, 0), ['forró'], 20, 50, false, EventStatus::Published],
            ['Puxadinho Moxuara', 'moxuara', ['galocanto', 'renato_da_rocinha'], 'moqueca_live', $now->copy()->addDays(18)->setTime(18, 0), ['samba', 'pagode'], 0, 0, true, EventStatus::Published],
            ['Barroco Sunset Sessions', 'barroco', ['mel_lisboa'], 'litoral_shows', $now->copy()->addDays(4)->setTime(19, 0), ['mpb', 'rock'], 30, 80, false, EventStatus::Published],
            ['Larika Indie Night', 'larika', ['eterea'], 'vibe_capixaba', $now->copy()->addDays(11)->setTime(22, 0), ['eletrônica', 'pop'], 25, 55, false, EventStatus::PendingApproval],
            ['Réveillon Antecipado', 'patrick_ribeiro', ['diogo_nogueira'], 'hz_entretenimento', $now->copy()->addDays(40)->setTime(22, 0), ['samba'], 100, 250, false, EventStatus::ChangesRequested],
        ];

        foreach ($rows as [$title, $venueHandle, $artistHandles, $promoterHandle, $start, $genres, $priceMin, $priceMax, $isFree, $status]) {
            $event = Event::query()->create([
                'title' => $title,
                'description' => "{$title} — evento ao vivo na Grande Vitória.",
                'cover_image_path' => $this->downloadCoverImagePath($title),
                'start_date_time' => $start,
                'venue_id' => $venues[$venueHandle]->id,
                'price_min' => $isFree ? null : $priceMin,
                'price_max' => $isFree ? null : $priceMax,
                'is_free' => $isFree,
                'currency' => 'BRL',
                'genres' => $genres,
                'ticket_url' => 'https://www.sympla.com.br/',
                'status' => $status,
            ]);

            $event->artists()->attach(array_map(fn (string $handle) => $artists[$handle]->id, $artistHandles));
            $event->promoters()->attach($promoters[$promoterHandle]->id);
        }
    }

    /**
     * Downloads a generic music/concert-ish stock image (picsum.photos, seeded by the event's
     * own title so re-seeding a fresh database yields the same picture per event) and stores it
     * through the same S3 disk/path prefix EventController uses for real uploads — so seeded
     * events render real covers instead of a null placeholder. Best-effort: seeding must not
     * fail just because the network/picsum is unavailable, so a failure logs a warning and
     * leaves the event's cover_image_path null, same as a publisher who skipped the upload.
     */
    private function downloadCoverImagePath(string $seed): ?string
    {
        try {
            $response = Http::timeout(5)->get('https://picsum.photos/seed/'.Str::slug($seed).'/800/450');

            if (! $response->successful()) {
                return null;
            }

            $path = 'events/covers/'.Str::uuid()->toString().'.jpg';

            // The s3 disk is configured with `throw` => false, so a write failure (e.g. bucket
            // unreachable) surfaces as a `false` return rather than an exception — checked
            // explicitly so a failed write never leaves a DB row pointing at a file that was
            // never actually stored.
            if (Storage::disk('s3')->put($path, $response->body()) === false) {
                return null;
            }

            return $path;
        } catch (Throwable $e) {
            Log::warning('DemoDataSeeder cover image download failed', ['exception' => $e, 'seed' => $seed]);

            return null;
        }
    }

    /**
     * @param  array<string, Promoter>  $promoters
     * @param  array<string, Venue>  $venues
     */
    private function seedUsers(array $promoters, array $venues): void
    {
        $fan1 = User::query()->create([
            'name' => 'Ana Rocha',
            'email' => 'ana.fan@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $fan2 = User::query()->create([
            'name' => 'Bruno Souza',
            'email' => 'bruno.fan@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $someEvents = Event::query()->orderBy('id')->limit(4)->get();
        $fan1->favoriteEvents()->attach($someEvents->take(2));
        $fan2->favoriteEvents()->attach($someEvents->slice(2, 2));

        $promoterUser = User::query()->create([
            'name' => 'Vibe Capixaba Produções',
            'email' => 'contato@vibecapixaba.example.com',
            'password' => 'password',
            'email_verified_at' => now(),
            'account_type' => AccountType::Promoter,
        ]);
        $promoters['vibe_capixaba']->update(['user_id' => $promoterUser->id]);

        $venueUser = User::query()->create([
            'name' => 'Espaço Patrick Ribeiro',
            'email' => 'contato@patrickribeiro.example.com',
            'password' => 'password',
            'email_verified_at' => now(),
            'account_type' => AccountType::Venue,
        ]);
        $venues['patrick_ribeiro']->update(['user_id' => $venueUser->id]);

        $pendingApplicant = User::query()->create([
            'name' => 'Casa Caipora',
            'email' => 'contato@casacaipora.example.com',
            'password' => 'password',
            'email_verified_at' => now(),
            'account_type' => AccountType::Venue,
        ]);
        VerificationApplication::query()->create([
            'user_id' => $pendingApplicant->id,
            'account_type' => AccountType::Venue,
            'venue_id' => $venues['casa_caipora']->id,
            'business_name' => 'Casa Caipora',
            'contact_email' => 'contato@casacaipora.example.com',
            'contact_phone' => '(27) 99999-1111',
            'social_link' => 'https://instagram.com/casacaipora',
            'status' => VerificationApplicationStatus::PendingReview,
        ]);
    }
}
