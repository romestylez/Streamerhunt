<?php
header("Content-Type: application/json");

$securePath = __DIR__;

$configFile = $securePath . "/config.php";
$stateFile  = $securePath . "/game_state.json";
$snapshotFile = $securePath . "/runner_snapshot.json";

if (!file_exists($configFile) || !file_exists($stateFile)) {
    http_response_code(500);
    echo json_encode(["error" => "Config or state missing"]);
    exit;
}

// ✅ Config als PHP laden
$config = include $configFile;

// ✅ State bleibt JSON
$state  = json_decode(file_get_contents($stateFile), true);


$allowedIP = $config["allowed_ip"] ?? null;

$action = $_GET["action"] ?? null;

// ✅ Remote Game Control
if (in_array($action, ["start", "pause"])) {

    // 🔒 Nur Start/Pause darf IP-geschützt sein
    if ($allowedIP && $_SERVER["REMOTE_ADDR"] !== $allowedIP) {
        http_response_code(403);
        echo json_encode(["error"=>"Forbidden"]);
        exit;
    }

    $state["status"] = ($action === "start" ? "running" : "paused");

    // 🔥 WICHTIG: Bei Pause Routen serverseitig zurücksetzen
    if ($action === "pause") {
        file_put_contents(
            $securePath . "/routes.json",
            json_encode([
                "runner" => [],
                "hunter" => []
            ], JSON_PRETTY_PRINT)
        );
    }

    file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));

    echo json_encode(["message" => "Game " . $state["status"]]);
    exit;
}

// ✅ Runner / Hunter setzen
if (in_array($action, ["set_runner","set_hunter"])) {

    if ($allowedIP && $_SERVER["REMOTE_ADDR"] !== $allowedIP) {
        http_response_code(403);
        echo json_encode(["error"=>"Forbidden"]);
        exit;
    }

    $id = $_GET["id"] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(["error"=>"Missing id"]);
        exit;
    }

    if ($action === "set_runner") $config["runner_id"] = $id;
    if ($action === "set_hunter") $config["hunter_id"] = $id;

    // ✅ Config als PHP speichern
    file_put_contents(
        $configFile,
        "<?php\nreturn " . var_export($config, true) . ";\n"
    );

    echo json_encode(["message" => "Updated", "id" => $id]);
    exit;
}

// ✅ Config liefern
if ($action === "config") {
    echo json_encode([
        "runner_id" => $config["runner_id"],
        "hunter_id" => $config["hunter_id"],
        "lat" => $config["lat"],
        "lng" => $config["lng"],
        "zoom" => $config["zoom"],
        "catch_meter" => $config["catch_meter"],
        "runner_fixed_position" => $config["runner_fixed_position"],
        "geo_fence_enabled" => $config["geo_fence_enabled"],
        "radius" => $config["radius"],
		"round_minutes" => $config["round_minutes"],
		"runner_runtime_minutes" => $config["runner_runtime_minutes"] ?? 30,
		"position_frequency_minutes" => $config["position_frequency_minutes"] ?? 1,
        "google_maps_key" => $config["google_maps_api_key"]
    ], JSON_PRETTY_PRINT);
    exit;
}


// ✅ Game State liefern
if ($action === "state") {
    echo json_encode($state, JSON_PRETTY_PRINT);
    exit;
}

// =============================
// Walking Distance Proxy
// =============================
if ($action === "distance") {
    $origin = $_GET["origin"] ?? null;
    $dest   = $_GET["destination"] ?? null;

    if (!$origin || !$dest) {
        echo json_encode(["error" => "missing parameters"]);
        exit;
    }

    // Wenn Game paused → Distance für OBS deaktivieren
    if ($state["status"] === "paused") {
        echo json_encode(["distance" => null, "paused" => true]);
        exit;
    }

    // Hole den API-Key aus der serverseitigen Config
    $key = $config["google_maps_api_key"];

    $url = "https://maps.googleapis.com/maps/api/directions/json?"
         . "origin=$origin&destination=$dest&mode=walking&key=$key";

    $json = @file_get_contents($url);
    $data = json_decode($json, true);

    if (!empty($data["routes"][0]["legs"][0]["distance"]["value"])) {
        echo json_encode([
            "distance" => $data["routes"][0]["legs"][0]["distance"]["value"],
            "paused"   => false
        ]);
        exit;
    }

    // API liefert nix → Fallback
    echo json_encode([
        "distance" => null,
        "paused"   => false
    ]);
    exit;
}

// =============================
// Route Storage
// =============================

$routeFile = $securePath . "/routes.json";

// Initialisieren falls fehlt
if (!file_exists($routeFile)) {
    file_put_contents($routeFile, json_encode([
        "runner" => [],
        "hunter" => []
    ], JSON_PRETTY_PRINT));
}

// Route abrufen
if ($action === "routes") {
    echo file_get_contents($routeFile);
    exit;
}

// Punkt hinzufügen
if ($action === "add_route") {
    $type = $_GET["type"] ?? null; // runner | hunter
    $lat  = $_GET["lat"] ?? null;
    $lng  = $_GET["lng"] ?? null;

    if (!in_array($type, ["runner","hunter"]) || !$lat || !$lng) {
        http_response_code(400);
        echo json_encode(["error"=>"invalid data"]);
        exit;
    }

    $routes = json_decode(file_get_contents($routeFile), true);
    $routes[$type][] = [
        "lat" => (float)$lat,
        "lng" => (float)$lng
    ];

    file_put_contents($routeFile, json_encode($routes, JSON_PRETTY_PRINT));
    echo json_encode(["ok"=>true]);
    exit;
}

if ($action === "save_runner_snapshot") {
    $slot = intval($_POST["slot"] ?? -1);
    $lat  = floatval($_POST["lat"] ?? 0);
    $lng  = floatval($_POST["lng"] ?? 0);

    if ($slot < 0) {
        echo json_encode(["error"=>"invalid slot"]);
        exit;
    }

    $current = file_exists($snapshotFile)
        ? json_decode(file_get_contents($snapshotFile), true)
        : null;

    if (!$current || $current["slot_index"] !== $slot) {
        file_put_contents(
            $snapshotFile,
            json_encode([
                "slot_index" => $slot,
                "lat" => $lat,
                "lng" => $lng,
                "timestamp" => time()
            ], JSON_PRETTY_PRINT)
        );
    }

    echo json_encode(["ok"=>true]);
    exit;
}

// =============================
// Hunter Snapshot liefern (für runner.html)
// =============================
if ($action === "hunter_snapshot") {
    $file = $securePath . "/hunter_snapshot.json";

    if (!file_exists($file)) {
        echo json_encode(null);
        exit;
    }

    echo file_get_contents($file);
    exit;
}

// =============================
// Runner Joker – Immediate Reveal Hunter
// =============================
if ($action === "runner_reveal_now") {

    if ($allowedIP && $_SERVER["REMOTE_ADDR"] !== $allowedIP) {
        http_response_code(403);
        echo json_encode(["error" => "Forbidden"]);
        exit;
    }

    $routeFile = $securePath . "/routes.json";
    $hunterSnapshotFile = $securePath . "/hunter_snapshot.json";

    if (!file_exists($routeFile)) {
        echo json_encode(["error" => "no routes"]);
        exit;
    }

    $routes = json_decode(file_get_contents($routeFile), true);

    if (empty($routes["hunter"])) {
        echo json_encode(["error" => "no hunter position"]);
        exit;
    }

    $last = end($routes["hunter"]);

    file_put_contents(
        $hunterSnapshotFile,
        json_encode([
            "lat" => $last["lat"],
            "lng" => $last["lng"],
            "timestamp" => time(),
            "source" => "runner_joker"
        ], JSON_PRETTY_PRINT)
    );

    echo json_encode([
        "ok" => true,
        "msg" => "runner reveal triggered"
    ]);
    exit;
}

// =============================
// Hunter Joker – Immediate Reveal Runner to Hunter
// =============================
if ($action === "hunter_reveal_now") {

    if ($allowedIP && $_SERVER["REMOTE_ADDR"] !== $allowedIP) {
        http_response_code(403);
        echo json_encode(["error" => "Forbidden"]);
        exit;
    }

    // Aktuelle Runner-Position aus bestehendem Snapshot
    if (!file_exists($snapshotFile)) {
        echo json_encode(["error" => "no runner position available"]);
        exit;
    }

    $snap = json_decode(file_get_contents($snapshotFile), true);

    if (!isset($snap["lat"], $snap["lng"])) {
        echo json_encode(["error" => "invalid snapshot"]);
        exit;
    }

    file_put_contents(
        $snapshotFile,
        json_encode([
            "slot_index" => time(), // egal, nur neu ≠ alt
            "lat" => $snap["lat"],
            "lng" => $snap["lng"],
            "timestamp" => time(),
            "source" => "hunter_joker"
        ], JSON_PRETTY_PRINT)
    );

    echo json_encode([
        "ok" => true,
        "msg" => "hunter reveal triggered"
    ]);
    exit;
}

if ($action === "runner_snapshot") {
    if (!file_exists($snapshotFile)) {
        echo json_encode(null);
        exit;
    }

    echo file_get_contents($snapshotFile);
    exit;
}


http_response_code(400);
echo json_encode(["error" => "Invalid action"]);
exit;
