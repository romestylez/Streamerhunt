<?php
$securePath = __DIR__

$configFile = $securePath . "/config.php";
$stateFile  = $securePath . "/game_state.json";
$rulesFile = $securePath . "/rules.json";

$rulesData = [
    "title" => "Streamer Hunt – Rules",
    "rules" => []
];

if (file_exists($rulesFile)) {
    $rulesData = json_decode(file_get_contents($rulesFile), true) ?? $rulesData;
}
$config = include $configFile;

$config["runner_runtime_minutes"] = $config["runner_runtime_minutes"] ?? 30;
$config["position_frequency_minutes"] = $config["position_frequency_minutes"] ?? 20;


$state  = json_decode(file_get_contents($stateFile), true);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
if (isset($_POST["save_rules"])) {

    $title = trim($_POST["rules_title"]);
    $rulesRaw = trim($_POST["rules_text"]);

    $rules = array_filter(array_map("trim", explode("\n", $rulesRaw)));

    file_put_contents(
        $rulesFile,
        json_encode([
            "title" => $title,
            "rules" => array_values($rules)
        ], JSON_PRETTY_PRINT)
    );

    exit(json_encode(["success" => true, "msg" => "rules_saved"]));
}

    if (isset($_POST["save_settings"])) {
		$config["runner_runtime_minutes"] = intval($_POST["runner_runtime_minutes"]);
		$config["position_frequency_minutes"] = intval($_POST["position_frequency_minutes"]);
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

    file_put_contents(
        $securePath . "/routes.json",
        json_encode(["runner"=>[], "hunter"=>[]], JSON_PRETTY_PRINT)
    );

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

.tabs {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
}

.tab {
  padding: 10px 18px;
  background: #222;
  border-radius: 8px;
  color: #c8e1ff;
  text-decoration: none;
  font-weight: bold;
  border: 1px solid #333;
}

.tab:hover {
  background: #2a3b55;
}

.tab.active {
  background: #007aff;
  border-color: #007aff;
  color: #fff;
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

<div class="tabs">
  <div class="tab active" id="tabBtnGame" onclick="showTab('game')">🎮 Game Control</div>
  <div class="tab" id="tabBtnRules" onclick="showTab('rules')">📜 Rules</div>
</div>

<div id="tab-game">
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

<label>Runner Runtime (Minutes):</label>
<input type="number" name="runner_runtime_minutes"
       min="1" max="1440"
       value="<?= intval($config['runner_runtime_minutes']) ?>">
<small>Maximale Laufzeit des Runners</small>

<label>Position Frequency (Minutes):</label>
<input type="number" name="position_frequency_minutes"
       min="1" max="60"
       value="<?= intval($config['position_frequency_minutes']) ?>">
<small>Wie oft neue Positionen verarbeitet werden</small>

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
</div>

<div id="tab-rules" style="display:none; max-width:600px;">

  <h2>📜 Rules Editor</h2>

  <label>Title</label>
  <input type="text" id="rulesTitle"
         value="<?= htmlspecialchars($rulesData["title"]) ?>">

  <label>Rules (one per line)</label>
  <textarea id="rulesText" rows="10" style="width:100%;"><?= 
    htmlspecialchars(implode("\n", $rulesData["rules"])) 
  ?></textarea>

  <br><br>
  <button type="button" onclick="saveRules()">💾 Save Rules</button>

</div>



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
    const data = new FormData();
    data.append(action, "1");

    fetch(window.location.href, {
        method: "POST",
        body: data
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

function showTab(tab) {
  document.getElementById("tab-game").style.display =
    tab === "game" ? "block" : "none";

  document.getElementById("tab-rules").style.display =
    tab === "rules" ? "block" : "none";

  document.getElementById("tabBtnGame")
    .classList.toggle("active", tab === "game");

  document.getElementById("tabBtnRules")
    .classList.toggle("active", tab === "rules");
}

function saveRules() {
  const data = new URLSearchParams();
  data.append("save_rules", "1");
  data.append("rules_title", document.getElementById("rulesTitle").value);
  data.append("rules_text", document.getElementById("rulesText").value);

  fetch("", {
    method: "POST",
    body: data
  })
  .then(r => r.json())
  .then(() => alert("✅ Rules saved"));
}


refreshStatusBox();
setInterval(refreshStatusBox, 2000);
showTab("game");
</script>

</body>
</html>
