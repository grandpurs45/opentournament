# OpenTournament

OpenTournament is an open source, local-first tournament management platform.

The first implementation focuses on running a real Molkky tournament while keeping the core generic enough for other games through rule plugins.

## Features

- Create tournaments.
- Add teams or players.
- Generate balanced pools.
- Generate round-robin matches.
- Enter and validate scores.
- Compute standings automatically.
- Use generic or Molkky scoring rules.
- Show a public TV display.
- Provide a mobile public view.
- Generate a per-tournament QR Code for mobile access.
- Export participants, matches and standings as CSV.
- Persist data in SQLite.

## Local Development With XAMPP

Place the project in your XAMPP `htdocs` directory and configure your local virtual host to point to this folder:

```apache
ServerName opentournament.local
DocumentRoot "C:/dev/xampp/htdocs/OpenTournament"
```

Then open:

```text
http://opentournament.local
```

The application creates `data/opentournament.sqlite` automatically.

## Docker

```bash
docker compose up -d
```

Then open:

```text
http://localhost:8080
```

SQLite data is stored in the `opentournament_data` Docker volume.

## Versioning

The current version is stored in `VERSION`.

Releases follow semantic versioning:

- `MAJOR`: breaking changes.
- `MINOR`: new compatible features.
- `PATCH`: compatible fixes.

Changes are tracked in `CHANGELOG.md`.

## Roadmap

See `ROADMAP.md`.

## License

MIT. See `LICENSE`.
