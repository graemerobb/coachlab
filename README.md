# CoachLab Local Setup

This project can run locally without Apache, Docker, or a remote server.

## Requirements

- PHP 8.1 or newer

## Start Locally

1. Copy the environment template:

```bash
cp .env.example .env
```

2. If you want the elite-player matching feature to work, add your OpenAI key to `.env`:

```bash
OPENAI_API_KEY=your_key_here
OPENAI_MODEL=gpt-4o
```

3. Start the local server:

```bash
./run_local.sh
```

On Windows, use:

```bat
run_local.bat
```

4. Open:

```text
http://127.0.0.1:8000/coachlab/coachlab.html
```

## Where Data Saves

- Main player/team data:
  - `public_html/api/player_data/coachlab_players.json`
- Snapshot files:
  - `public_html/api/player_data/snapshots/...`
- Reflection files:
  - `public_html/api/player_data/reflections/...`

## Session Catalogue

The Sessions tab reads from:

- `public_html/api/session_catalogue.csv`

## Sharing With Someone Else

You can zip the repo and send it to them. They only need:

- PHP installed
- the repo contents
- to run `./run_local.sh` on macOS/Linux or `run_local.bat` on Windows

If they do not need elite-player matching, they can leave `OPENAI_API_KEY` blank.
