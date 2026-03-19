# 🗺️ Streamer Hunt – Runner vs. Hunter (Map & Joker System)

A file-based **PHP + HTML** project for an IRL  
**“Runner vs. Hunter”** stream game with controlled position reveals.

Designed for **OBS Browser Sources**, manual **Joker reveals** and a secure, server-side game flow.

---

## 📦 Components

### 🧍‍♂️ Views (Overlays)

- 🗺️ **index.html**  
  Google Maps overlay showing runner & hunter positions, live distance HUD,
  pause overlay and a **CAUGHT!** alert.

- 🎯 **distance.html**  
  Minimal OBS-friendly distance-only overlay (large number, transparent background).

- 🔌 **api.php**  
  JSON API used by the overlays:
  - configuration & game state

- 🎛️ **control.php**  
  Simple control panel to:
  - edit settings/rules
  - start / pause the game

- 🗺️ **hunter.html**  
  Map view **for the Hunters**  
  → shows the **Runner position**  
  → position updates:
  - automatically by timer
  - **manually via Hunter Joker**

- 🏃 **runner.html**  
  Map view **for the Runner**  
  → shows the **Hunter position**  
  → **ONLY visible when a Runner Joker is used**  
  → no countdown, no automatic updates

---

### 🔌 Backend

- 🔌 **api.php**  
  Central JSON API:
  - configuration & game state
  - route storage
  - snapshot delivery
  - **Joker reveal actions**
  - Google Directions proxy (distance)

- 🎛️ **control.php**  
  Control panel to:
  - edit game settings
  - start / pause rounds
  - manage runner / hunter IDs

- ⚙️ **config.php**, **game_state.json**  
  File-based configuration & runtime state  
  (intentionally excluded from public repos)

---

## 🧠 Core Concept

### 🔍 Snapshots instead of live tracking

Positions are **not streamed live** to the opponents.

Instead:
- positions are written to **snapshot JSON files**
- views poll these snapshots
- **Jokers force a snapshot update**

This guarantees:
- fair gameplay
- no timer manipulation
- deterministic reveals

---

## 📂 Snapshot Files

| File | Contains | Used by |
|----|--------|--------|
| runner_snapshot.json | Runner position | hunter.html |
| hunter_snapshot.json | Hunter position | runner.html |

---

## 🃏 Joker System

### 🎯 Hunter Joker (Hunters reveal the Runner)

```
GET api.php?action=hunter_reveal_now
```

**Effect:**
- updates `runner_snapshot.json`
- `hunter.html` immediately shows the Runner position
- countdown continues normally

---

### 🏃 Runner Joker (Runner reveals the Hunters)

```
GET api.php?action=runner_reveal_now
```

**Effect:**
- updates `hunter_snapshot.json`
- `runner.html` immediately shows the Hunter position
- stays visible until the next Runner Joker

---

### 🔐 Security

All state-changing endpoints are **IP-restricted** via:

```php
"allowed_ip" => "YOUR_ALLOWED_IP"
```

---

## 🔌 API Overview

### 📖 Read-only

- `GET api.php?action=config`
- `GET api.php?action=state`
- `GET api.php?action=runner_snapshot`
- `GET api.php?action=hunter_snapshot`
- `GET api.php?action=routes`
- `GET api.php?action=distance&origin=LAT,LNG&destination=LAT,LNG`

---

### ✍️ State-changing (IP protected)

- `GET api.php?action=start`
- `GET api.php?action=pause`
- `GET api.php?action=hunter_reveal_now`
- `GET api.php?action=runner_reveal_now`
- `GET api.php?action=set_runner&id=TWITCH_ID`
- `GET api.php?action=set_hunter&id=TWITCH_ID`

---

## ⚙️ Configuration (config.php)

```php
return [
  "google_maps_api_key" => "YOUR_GOOGLE_API_KEY",
  "runner_id" => "TWITCH_RUNNER_ID",
  "hunter_id" => "TWITCH_HUNTER_ID",
  "lat" => 7.913042,
  "lng" => 98.33229,
  "zoom" => 12,
  "catch_meter" => 10,
  "runner_runtime_minutes" => 30,
  "position_frequency_minutes" => 20,
  "allowed_ip" => "ALLOWED_IP_FOR_JOKERS",
];
```

---

## 🎥 OBS Usage

| Purpose | File |
|------|------|
| Hunter map | hunter.html |
| Runner map | runner.html |
| Control panel | control.php |

- Set OBS Browser Source background to **transparent**
- All overlays are optimized for fullscreen usage

---

## 🔐 Security Notes

- `control.php` has **no authentication**
- Protect it using:
  - HTTP auth
  - IP allowlists
  - VPN
  - reverse proxy authentication

---

## 📜 License

Choose a license (e.g. **MIT**) and add a `LICENSE` file  
if you want others to reuse or fork the project.
