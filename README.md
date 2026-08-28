<a id="readme-top"></a>

[![Issues][issues-shield]][issues-url]
[![Forks][forks-shield]][forks-url]
[![Stargazers][stars-shield]][stars-url]
[![License: TBD][license-shield]][license-url]

<br />
<div align="center">
  <h3 align="center">qor-api</h3>

  <p align="center">
    Laravel API powering QOR — auth, event discovery, approval workflow, and moderation for Greater Vitória's music-event platform.
    <br />
    <a href="https://github.com/derlandyb/QOR/tree/main/.specs"><strong>Explore the specs »</strong></a>
    <br />
    <br />
    <a href="https://github.com/derlandyb/QOR">Root QOR repo</a>
    &middot;
    <a href="https://github.com/derlandyb/qor-api/issues/new?labels=bug">Report Bug</a>
    &middot;
    <a href="https://github.com/derlandyb/qor-api/issues/new?labels=enhancement">Request Feature</a>
  </p>
</div>

<details>
  <summary>Table of Contents</summary>
  <ol>
    <li><a href="#about-the-project">About The Project</a>
      <ul><li><a href="#built-with">Built With</a></li></ul>
    </li>
    <li><a href="#getting-started">Getting Started</a>
      <ul>
        <li><a href="#prerequisites">Prerequisites</a></li>
        <li><a href="#installation">Installation</a></li>
      </ul>
    </li>
    <li><a href="#usage">Usage</a></li>
    <li><a href="#roadmap">Roadmap</a></li>
    <li><a href="#contributing">Contributing</a></li>
    <li><a href="#license">License</a></li>
    <li><a href="#contact">Contact</a></li>
    <li><a href="#acknowledgments">Acknowledgments</a></li>
  </ol>
</details>

<!-- ABOUT THE PROJECT -->
## About The Project

<!-- [product-screenshot]: no screenshot yet — no application code exists in this repo yet, this is scaffolding only -->

`qor-api` is the Laravel backend for **QOR**, a music-event discovery platform for the Greater Vitória region (Vitória, Vila Velha, Serra, Cariacica). This repo owns:

* Auth (Sanctum, split guards for fans vs. Venue/Promoter/Super Admin)
* Event Discovery's public read API (`/api/v1/events`)
* Venue/Promoter self-registration and the Super Admin approval workflow (`/api/admin/v1`)
* Favorites, friends, notifications dispatch, and publishing-plan/quota enforcement, added in later milestones

Clean Architecture is mandatory here — the domain layer (`src/Domain/**`) has zero framework dependency; Eloquent/Sanctum/HTTP live in the outer adapter layers only. See the root [QOR](https://github.com/derlandyb/QOR) repo's `.specs/project/ARCHITECTURE.md` for the full system design this repo implements.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

### Built With

* [![Laravel][Laravel.com]][Laravel-url]
* [![PHP][PHP.com]][PHP-url]
* [![PostgreSQL][PostgreSQL.com]][PostgreSQL-url]
* [![Docker][Docker.com]][Docker-url]

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- GETTING STARTED -->
## Getting Started

**No application code exists in this repo yet** — this README documents the intended setup once scaffolding lands (see [Roadmap](#roadmap)), it does not describe a working app today.

### Prerequisites

* PHP 8.4
* Composer
* Docker + Docker Compose (this repo is one of the four Dockerized services in the root Makefile's stack — everything except `qor-mobile`)
* PostgreSQL 16 (provided via Docker Compose, not installed locally)

### Installation

Once scaffolded, this repo is intended to be driven entirely from the root `QOR` repo's Makefile — `docker compose` is never invoked directly per-service:

```sh
git clone https://github.com/derlandyb/QOR.git
cd QOR
make up
```

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- USAGE EXAMPLES -->
## Usage

This repo is implemented task-by-task from the root `QOR` repo's spec-driven plan. See [`.specs/tasks/api.md`](https://github.com/derlandyb/QOR/blob/main/.specs/tasks/api.md) for the full granular task breakdown (entities, use cases, endpoints, gate checks) across every milestone, and [`.specs/features/`](https://github.com/derlandyb/QOR/tree/main/.specs/features) for the requirements each task traces back to.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- TESTING TOOLING -->
## Testing tooling

- **Postman collection** — [`docs/postman/qor-api-mvp-core.postman_collection.json`](docs/postman/qor-api-mvp-core.postman_collection.json) covers every registered route, grouped by surface (Auth/Profile/Events for fans, Venue/Promoter/Events/Approvals/Dashboard for admin). Import it alongside the matching environment file for your target — [`docs/postman/qor-api-dev.postman_environment.json`](docs/postman/qor-api-dev.postman_environment.json), `-stage.`, or `-prod.` — and set the `fan_token`/`admin_token` environment variables from a `/auth/login` or `/auth/register` response.
- **k6 load test** — [`tests/load/mvp-core.k6.js`](tests/load/mvp-core.k6.js) exercises the endpoints named in `ARCHITECTURE.md` §8.8 (fan event list/detail, fan login, admin event submission, admin approval queue). Run it with `k6 run api/tests/load/mvp-core.k6.js` (see the file header for env vars); its thresholds are placeholders pending a real SLO from ops.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- ROADMAP -->
## Roadmap

- [ ] **MVP Core** — repo/CI scaffolding, auth, public event-discovery API, Venue/Promoter registration + approval workflow
- [ ] **Social & Notifications** — favorites, friends graph, push/email notification dispatch (FCM + AWS SES)
- [ ] **Monetization** — publishing plans, quota enforcement, Super Admin plan CRUD

See the [open issues](https://github.com/derlandyb/qor-api/issues) for a full list of proposed features (and known issues), and the root repo's `.specs/project/ROADMAP.md` for milestone status.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- CONTRIBUTING -->
## Contributing

Contributions make the open source community an amazing place to learn, inspire, and create. Any contributions you make are **greatly appreciated**.

If you have a suggestion, please fork the repo and create a pull request. You can also simply open an issue. Don't forget to give the project a star! Thanks again!

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- LICENSE -->
## License

No license has been chosen yet for this project. All rights reserved until a license is added.

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- CONTACT -->
## Contact

Derlandy Belchior - derlandy.belchior@gmail.com

Project Link: [https://github.com/derlandyb/qor-api](https://github.com/derlandyb/qor-api)

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- ACKNOWLEDGMENTS -->
## Acknowledgments

* [Best-README-Template](https://github.com/othneildrew/Best-README-Template)

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- MARKDOWN LINKS & IMAGES -->
[issues-shield]: https://img.shields.io/github/issues/derlandyb/qor-api.svg?style=for-the-badge
[issues-url]: https://github.com/derlandyb/qor-api/issues
[forks-shield]: https://img.shields.io/github/forks/derlandyb/qor-api.svg?style=for-the-badge
[forks-url]: https://github.com/derlandyb/qor-api/network/members
[stars-shield]: https://img.shields.io/github/stars/derlandyb/qor-api.svg?style=for-the-badge
[stars-url]: https://github.com/derlandyb/qor-api/stargazers
[license-shield]: https://img.shields.io/badge/license-TBD-lightgrey.svg?style=for-the-badge
[license-url]: #license
[Laravel.com]: https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white
[Laravel-url]: https://laravel.com
[PHP.com]: https://img.shields.io/badge/PHP_8.4-777BB4?style=for-the-badge&logo=php&logoColor=white
[PHP-url]: https://www.php.net
[PostgreSQL.com]: https://img.shields.io/badge/PostgreSQL-4169E1?style=for-the-badge&logo=postgresql&logoColor=white
[PostgreSQL-url]: https://www.postgresql.org
[Docker.com]: https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white
[Docker-url]: https://www.docker.com
