<?php
header("Content-Type: application/json");

$basePath = __DIR__;

$configFile = $basePath . "/config.php";
$stateFile  = $basePath . "/game_state.json";

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

    if ($allowedIP && $_SERVER["REMOTE_ADDR"] !== $allowedIP) {
        http_response_code(403);
        echo json_encode(["error"=>"Forbidden"]);
        exit;
    }

    $state["status"] = ($action === "start" ? "running" : "paused");
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


http_response_code(400);
echo json_encode(["error" => "Invalid action"]);
exit;


