<?php
$securePath = __DIR__;

$configFile = $securePath . "/config.php";
$stateFile  = $securePath . "/game_state.json";

$config = include $configFile;

$state  = json_decode(file_get_contents($stateFile), true);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (isset($_POST["save_settings"])) {

        $config["runner_id"] = $_POST["runner_id"];
        $config["hunter_id"] = $_POST["hunter_id"];
        $config["runner_fixed_position"] = ($_POST["runner_fixed_position"] === "1");
        $config["geo_fence_enabled"] = ($_POST["geo_fence_enabled"] === "1");
        $config["radius"] = intval($_POST["radius"]);
        $config["lat"] = floatval($_POST["lat"]);
        $config["lng"] = floatval($_POST["lng"]);
        $config["catch_meter"] = intval($_POST["catch_meter"]);
		$config["round_minutes"] = intval($_POST["round_minutes"]);
        
		file_put_contents(
            $configFile,
            "<?php\nreturn " . var_export($config, true) . ";\n"
        );

        exit(json_encode(["success" => true, "msg" => "saved"]));
    }

    if (isset($_POST["pause_game"])) {
    $state["status"] = "paused";
    file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));

    // 🔥 ROUTES BEI PAUSE ZURÜCKSETZEN
    file_put_contents(
        $securePath . "/routes.json",
        json_encode([
            "runner" => [],
            "hunter" => []
        ], JSON_PRETTY_PRINT)
    );

    exit(json_encode(["success" => true, "msg" => "paused"]));
}


    if (isset($_POST["start_game"])) {
    $state["status"] = "running";
    $state["round_start"] = time();
    file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
    exit(json_encode(["success" => true, "msg" => "running"]));
}
}

$currentStatus = $state["status"];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Streamer Hunt Control Panel</title>
<link rel="icon" href="favicon.ico">

<script>
function loadMapsScripts() {
    const s = document.createElement("script");
    s.src = "https://maps.googleapis.com/maps/api/js?key=<?= $config['google_maps_api_key'] ?>&libraries=places&callback=initAutocomplete";
    s.async = true;
    document.head.appendChild(s);
}
loadMapsScripts();
</script>

<style>
body {
  font-family: Arial, sans-serif;
  background-color: #111;
  color: #eee;
  padding: 30px;
}
input[type=text], input[type=number] {
  width: 250px;
  padding: 6px;
  margin: 4px 0 12px;
  border-radius: 6px;
  border: none;
}
label { display: block; margin-top: 12px; }
small { color: #bbb; }
button {
  background-color: #333;
  color: #fff;
  padding: 12px 18px;
  border: none;
  cursor: pointer;
  border-radius: 8px;
  font-size: 16px;
  margin-right: 10px;
}
button:hover { background-color: #444; }
.status-box {
  padding: 12px;
  margin-bottom: 16px;
  border-radius: 6px;
  font-weight: bold;
  background: #222;
}
.green { color: #00ff7f; }
.red { color: #ff5555; }
.range-display {
  font-size: 16px;
  margin-left: 10px;
}
</style>

</head>
<body>

<h1>🎯 Streamer Hunt Control Panel</h1>

<div id="status-text" class="status-box">
  Status: <span id="status-value" class="<?= $currentStatus === 'running' ? 'green' : 'red' ?>">
    <?= strtoupper($currentStatus) ?>
  </span>
</div>



<form id="controlForm" method="POST">

  <label>Runner Twitch ID:</label>
  <input type="text" name="runner_id" value="<?= htmlspecialchars($config["runner_id"]) ?>">

  <label>Hunter Twitch ID:</label>
  <input type="text" name="hunter_id" value="<?= htmlspecialchars($config["hunter_id"]) ?>">

  <label>Mode:</label>
<select name="runner_fixed_position" id="modeSelect">
    <option value="0" <?= !$config["runner_fixed_position"] ? "selected" : "" ?>>Streamer Hunt (Live GPS)</option>
    <option value="1" <?= $config["runner_fixed_position"] ? "selected" : "" ?>>Excursion (Fixed Goal)</option>
</select>

<label>Round Timer (Minutes):</label>
<input type="number" name="round_minutes" min="1" max="300"
       value="<?= $config['round_minutes'] ?? 30 ?>">
<small>Countdown starts when game is running</small>


<label>Search Address:</label>
<input type="text" id="addressInput" placeholder="Enter address...">

<label id="posLabel"></label>
<input type="text" name="lat" value="<?= htmlspecialchars($config["lat"]) ?>" placeholder="Latitude">
<br>
<input type="text" name="lng" value="<?= htmlspecialchars($config["lng"]) ?>" placeholder="Longitude">

<label>Catch Meter:</label>
<input type="range" min="1" max="50" name="catch_meter"
       value="<?= $config["catch_meter"] ?>"
       oninput="document.getElementById('cmv').innerText = this.value">
<span id="cmv" class="range-display"><?= $config["catch_meter"] ?></span> m

<label>Geo-Fence:</label>
<select name="geo_fence_enabled" id="geoFenceSelect">
    <option value="0" <?= !$config["geo_fence_enabled"] ? "selected" : "" ?>>Disabled</option>
    <option value="1" <?= $config["geo_fence_enabled"] ? "selected" : "" ?>>Enabled</option>
</select>

<label>Radius:</label>
<input type="range" min="1" max="20" name="radius" id="radiusSlider"
       value="<?= intval($config['radius']) ?>"
       oninput="document.getElementById('radv').innerText = this.value">
<span id="radv" class="range-display"><?= intval($config["radius"]) ?></span>
<span id="radunit" class="range-display">km</span>

<br><br>

<button type="button" id="startPauseBtn"></button>
<button type="button" onclick="saveSettings()">💾 Save Settings</button>

</form>

<script>
let autocomplete;

function initAutocomplete() {
    const input = document.getElementById("addressInput");
    autocomplete = new google.maps.places.Autocomplete(input);

    autocomplete.addListener("place_changed", () => {
        const place = autocomplete.getPlace();
        if (!place.geometry) return;
        document.querySelector('[name="lat"]').value = place.geometry.location.lat().toFixed(6);
        document.querySelector('[name="lng"]').value = place.geometry.location.lng().toFixed(6);
    });
}

function updateModeLabels() {
    const isExcursion = document.getElementById("modeSelect").value === "1";
    document.getElementById("posLabel").innerHTML =
        isExcursion ? 'Goal Position:' : 'Geo-Fence Center:';
}

function toggleRadiusUI() {
    const enabled = document.getElementById("geoFenceSelect").value === "1";
    const opacity = enabled ? "1" : "0.3";
    document.getElementById("radiusSlider").disabled = !enabled;
    document.getElementById("radv").style.opacity = opacity;
    document.getElementById("radunit").style.opacity = opacity;
}

updateModeLabels();
toggleRadiusUI();

document.getElementById("modeSelect").addEventListener("change", updateModeLabels);
document.getElementById("geoFenceSelect").addEventListener("change", toggleRadiusUI);

function showMessage(msg) {
    const box = document.getElementById("msg-box");
    box.innerText = msg;
    setTimeout(() => box.innerText = "", 2500);
}

// AJAX Start/Pause Button
function sendAction(action) {
    fetch("", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: action + "=1"
    })
    .then(r => r.json())
    .then(j => {
        refreshStatusBox();
        showMessage(j.msg);
    });
}

document.getElementById("startPauseBtn").addEventListener("click", () => {
    const status = document.getElementById("status-value").textContent.toLowerCase();
    sendAction(status === "running" ? "pause_game" : "start_game");
});

// AJAX Save Settings
function saveSettings() {
    const formData = new FormData(document.getElementById("controlForm"));
    formData.append("save_settings", "1");

    fetch("", {
        method: "POST",
        body: formData
    })
    .then(r => r.json())
    .then(j => showMessage("✅ Settings saved"));
}


// Live Status Refresh
function refreshStatusBox() {
    fetch("api.php?action=state&_=" + Date.now())
      .then(r => r.json())
      .then(s => {
          const val = document.getElementById("status-value");
          val.textContent = s.status.toUpperCase();
          val.className = s.status === "running" ? "green" : "red";

          const btn = document.getElementById("startPauseBtn");
          if (s.status === "running") {
              btn.textContent = "⏸ Pause Game";
              btn.style.background = "#900";
          } else {
              btn.textContent = "▶ Start Round";
              btn.style.background = "#007700";
          }
      });
}

refreshStatusBox();
setInterval(refreshStatusBox, 2000);
</script>

</body>
</html>
