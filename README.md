# OpenTournament

OpenTournament is an open source, local-first tournament management platform.

The first implementation focuses on running a real Molkky tournament while keeping the core generic enough for other games through rule plugins.

## Features

- Create tournaments.
- Add teams or players.
- Generate balanced pools.
- Generate round-robin matches.
- Use pools, full round-robin, or pools with final stages.
- Enter and validate scores.
- Track matches and score entry by field.
- Compute standings automatically.
- Use generic or Molkky scoring rules.
- Show a public TV display.
- Provide a mobile public view.
- Show public rules from the active scoring plugin.
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

Clone the repository on the server:

```bash
git clone https://github.com/grandpurs45/opentournament.git
cd opentournament
```

Start the application:

```bash
docker compose up -d
```

Then open:

```text
http://localhost:8080
```

SQLite data is stored in the `opentournament_data` Docker volume.

Set `HTTP_PORT` when port `8080` is already used:

```bash
HTTP_PORT=8090 APP_URL=http://localhost:8090 docker compose up -d --build
```

For a persistent setup, create a local `.env` file:

```env
HTTP_PORT=8090
APP_URL=http://your-server-ip-or-domain:8090
```

`APP_URL` must be the public URL used by phones on the same network. The QR Codes are generated from this value.

You can also set it directly in Compose:

```yaml
environment:
  APP_URL: http://your-server-ip-or-domain:8090
```

To update an existing Docker installation:

```bash
cd opentournament
git pull
docker compose up -d --build
```

The SQLite Docker volume is preserved by this update command.

## Versioning

The current version is stored in `VERSION`.

Releases follow semantic versioning:

- `MAJOR`: breaking changes.
- `MINOR`: new compatible features.
- `PATCH`: compatible fixes.

Changes are tracked in `CHANGELOG.md`.

## Tests

```bash
php tests/v1_hardening_test.php
```

## Team Import

In a tournament, open `Participants`, then use `Import rapide`.

Paste one team per line. To include first names, add them after a semicolon:

```text
Equipe 1; Alice; Bob
Equipe 2; Chloe; David
Equipe 3
```

Blank lines are ignored. Imported entries are created as teams. First names can be separated with semicolons, commas or `|`.

## Roadmap

See `ROADMAP.md`.

## License

MIT. See `LICENSE`.
