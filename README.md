# Streamer Hunt (Map + Distance Overlays)

A small, file-based PHP + HTML project for an IRL “runner vs. hunter” stream overlay:

- **`index.html`** – Google Maps overlay showing runner/hunter positions, a distance HUD, pause overlay, and a “CAUGHT!” alert.
- **`distance.html`** – Minimal OBS-friendly distance-only overlay (big number).
- **`api.php`** – JSON API used by the overlays (config/state + a server-side Google Directions proxy).
- **`control.php`** – Simple control panel to edit settings and start/pause the game.
- **`config.php`** + **`game_state.json`** – File-based configuration and runtime state.

> This repository intentionally ships with **placeholder values** (no real API keys / IDs).  
> Do **not** commit your real production config to a public repo.

---

## Demo Screens

- Map overlay: `index.html`
- Distance-only overlay: `distance.html`

Both are designed to be used as **Browser Sources in OBS**.

---

## How it works

### Realtime player locations
The front-end uses the **RealtimeIRL** browser library:

- CDN: `https://cdn.jsdelivr.net/npm/@rtirl/api@latest/lib/index.min.js`

It subscribes to location updates for:

- `runner_id`
- `hunter_id`

### Google Maps + distance
- The map uses the **Google Maps JavaScript API**.
- Walking distance is fetched via **`api.php?action=distance`**, which calls the **Google Directions API** server-side and returns the distance in meters.
- Both `index.html` and `distance.html` use this server-side proxy (no Google API key is exposed in browser JavaScript).

When the game is **paused**, the API returns `{"paused": true}` so overlays can hide distance/markers.

---

## Requirements

- A web server that can run PHP (Apache/Nginx/IIS) **or** PHP’s built-in dev server
- PHP 7.4+ (PHP 8.x recommended)
- A Google Maps API key with billing enabled:
  - **Maps JavaScript API**
  - **Directions API**

---

## Quick start (local)

1. Put all files into one folder (for example `public/`).
2. Start PHP’s built-in server in that folder:

   ```bash
   php -S 127.0.0.1:8000
   ```

3. Open in your browser:
   - Map overlay: `http://127.0.0.1:8000/index.html`
   - Distance overlay: `http://127.0.0.1:8000/distance.html`
   - Control panel: `http://127.0.0.1:8000/control.php`

---

## Configuration

Edit `config.php`:

```php
return [
  "google_maps_api_key"      => "YOUR_GOOGLE_API_KEY",
  "runner_id"                => "TWITCH_ID_HERE",
  "hunter_id"                => "TWITCH_ID_HERE",
  "geo_fence_enabled"        => true,
  "runner_fixed_position"    => false,
  "lat"                      => 7.913042,
  "lng"                      => 98.33229,
  "radius"                   => 15,
  "zoom"                     => 12,
  "alarm"                    => true,
  "catch_meter"              => 10,
  "allowed_ip"               => "ALLOWED_IP_FOR_API_ACCESS",
];
```

### Important fields

- `google_maps_api_key`  
  Used by:
  - Google Maps JS (map rendering)
  - Google Directions API (walking distance proxy in `api.php?action=distance`)

- `runner_id` / `hunter_id`  
  Twitch IDs used by RealtimeIRL to subscribe to location updates.

- `runner_fixed_position`  
  If `true`, the runner marker is fixed at `lat/lng` (useful for a static “base” position).

- `catch_meter`  
  When distance ≤ this value, the UI shows **CAUGHT!**.

- `allowed_ip`  
  Optional IP restriction for **state-changing API actions**:
  - `start`, `pause`, `set_runner`, `set_hunter`  
  If empty/placeholder, those actions are effectively unrestricted.

---

## API

All endpoints are served by `api.php`:

### Read-only endpoints

- `GET api.php?action=config`  
  Returns the overlay configuration (including `google_maps_key` for the map script loader).

- `GET api.php?action=state`  
  Returns the current game state from `game_state.json`:
  ```json
  { "status": "paused" }
  ```

- `GET api.php?action=distance&origin=LAT,LNG&destination=LAT,LNG`  
  Returns:
  - `distance` (meters) when running
  - `paused: true` when paused

### State-changing endpoints (optionally IP-restricted)

- `GET api.php?action=start`
- `GET api.php?action=pause`
- `GET api.php?action=set_runner&id=TWITCH_ID`
- `GET api.php?action=set_hunter&id=TWITCH_ID`

---

## Control panel (`control.php`)

`control.php` is a simple settings UI that writes to:

- `config.php` (via `var_export()`)
- `game_state.json`

It supports:
- saving map/catch settings
- start/pause game state

> **Security note:** `control.php` has **no authentication**.  
> If you deploy this publicly, protect it (basic auth, IP allowlist, VPN, reverse proxy auth, etc.).

---

## OBS usage tips

- Add a **Browser Source** pointing to `distance.html` for a clean distance HUD.
- Add another **Browser Source** pointing to `index.html` for the map overlay.
- For transparent background overlays, ensure OBS “Background Color” is transparent and the page CSS uses transparent backgrounds (already done).

---
