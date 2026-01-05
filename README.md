# 🗺️ Streamer Hunt – Map & Distance Overlays

A small, file-based **PHP + HTML** project for an IRL  
**“runner vs. hunter”** stream overlay setup.

Designed for **OBS Browser Sources** with live map tracking, distance display,
pause handling and a catch alert.

---

## 📦 Components

- 🗺️ **`index.html`**  
  Google Maps overlay showing runner & hunter positions, live distance HUD,
  pause overlay and a **CAUGHT!** alert.

- 🎯 **`distance.html`**  
  Minimal OBS-friendly distance-only overlay (large number, transparent background).

- 🔌 **`api.php`**  
  JSON API used by the overlays:
  - configuration & game state
  - **server-side Google Directions proxy** for walking distance

- 🎛️ **`control.php`**  
  Simple control panel to:
  - edit settings
  - start / pause the game

- ⚙️ **`config.php`** + **`game_state.json`**  
  File-based configuration and runtime state.

> ⚠️ This repository intentionally ships with **placeholder values only**  
> (no real API keys or Twitch IDs).  
> **Never commit your real production config to a public repository.**

---

## 🎥 Demo / Usage

- 🗺️ Map overlay: `index.html`
- 🎯 Distance-only overlay: `distance.html`

Both are designed to be added as **Browser Sources in OBS**.

---

## 🧠 How it works

### 📡 Realtime player locations
The front-end uses the **RealtimeIRL** browser library:

- CDN:  
  ```
  https://cdn.jsdelivr.net/npm/@rtirl/api@latest/lib/index.min.js
  ```

It subscribes to live location updates for:
- `runner_id`
- `hunter_id`

---

### 🗺️ Google Maps & distance calculation

- The map itself uses the **Google Maps JavaScript API**
- Walking distance is fetched via  
  **`api.php?action=distance`**
- The API calls the **Google Directions API server-side**
- Distance is returned in **meters**

✅ No Google API key is exposed in browser JavaScript  
✅ Same distance logic is used by **both** overlays

When the game is **paused**, the API returns:

```json
{ "paused": true }
```

Overlays then hide markers and distance automatically.

---

## 🔧 Requirements

- Web server with PHP support  
  (Apache / Nginx / IIS **or** PHP built-in server)
- PHP **7.4+** (PHP 8.x recommended)
- Google Maps API key with billing enabled:
  - **Maps JavaScript API**
  - **Directions API**

---

## 🚀 Quick Start (Local)

1. Put all files into one folder (e.g. `public/`)
2. Start PHP’s built-in web server:

   ```bash
   php -S 127.0.0.1:8000
   ```

3. Open in your browser:
   - 🗺️ Map overlay  
     `http://127.0.0.1:8000/index.html`
   - 🎯 Distance overlay  
     `http://127.0.0.1:8000/distance.html`
   - 🎛️ Control panel  
     `http://127.0.0.1:8000/control.php`

---

## ⚙️ Configuration

Edit **`config.php`**:

```php
return [
  "google_maps_api_key"   => "YOUR_GOOGLE_API_KEY",
  "runner_id"             => "TWITCH_ID_HERE",
  "hunter_id"             => "TWITCH_ID_HERE",
  "geo_fence_enabled"     => true,
  "runner_fixed_position" => false,
  "lat"                   => 7.913042,
  "lng"                   => 98.33229,
  "radius"                => 15,
  "zoom"                  => 12,
  "alarm"                 => true,
  "catch_meter"           => 10,
  "allowed_ip"            => "ALLOWED_IP_FOR_API_ACCESS",
];
```

---

### 🔑 Important fields

- 🔐 **`google_maps_api_key`**  
  Used for:
  - Google Maps JS (map rendering)
  - Google Directions API (distance proxy)

- 🎮 **`runner_id` / `hunter_id`**  
  Twitch IDs used by RealtimeIRL for live GPS tracking.

- 📍 **`runner_fixed_position`**  
  If `true`, the runner is fixed at `lat/lng`
  (useful for static bases or checkpoints).

- 🎯 **`catch_meter`**  
  When distance ≤ this value → **CAUGHT!** is triggered.

- 🌐 **`allowed_ip`**  
  Optional IP restriction for state-changing API actions:
  - `start`
  - `pause`
  - `set_runner`
  - `set_hunter`

---

## 🔌 API Endpoints (`api.php`)

### 📖 Read-only

- `GET api.php?action=config`  
  Returns overlay configuration (incl. map key for JS loader)

- `GET api.php?action=state`  
  Returns current game state:
  ```json
  { "status": "paused" }
  ```

- `GET api.php?action=distance&origin=LAT,LNG&destination=LAT,LNG`  
  Returns:
  - `distance` (meters) when running
  - `paused: true` when paused

---

### ✍️ State-changing (optional IP-restricted)

- `GET api.php?action=start`
- `GET api.php?action=pause`
- `GET api.php?action=set_runner&id=TWITCH_ID`
- `GET api.php?action=set_hunter&id=TWITCH_ID`

---

## 🎛️ Control Panel (`control.php`)

`control.php` provides a simple UI to:

- update map & catch settings
- start / pause the game

It writes changes to:
- `config.php`
- `game_state.json`

> 🔒 **Security note**  
> `control.php` has **no authentication**.  
> If deployed publicly, protect it using:
> - HTTP auth
> - IP allowlists
> - VPN
> - reverse proxy auth

---

## 🎥 OBS Usage Tips

- Add **`distance.html`** as a Browser Source for a clean distance HUD
- Add **`index.html`** as a Browser Source for the live map
- Set OBS Browser Source background to **transparent**
- CSS is already optimized for overlays

---

## 📜 License

Choose a license (e.g. **MIT**) and add a `LICENSE` file
if you want others to reuse or fork the project.
