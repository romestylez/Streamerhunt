<?php
$rulesFile = __DIR__

$data = [
    "title" => "Game Rules",
    "rules" => []
];

if ($rulesFile && file_exists($rulesFile)) {
    $json = json_decode(file_get_contents($rulesFile), true);
    if (is_array($json)) {
        $data = $json;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($data["title"]) ?></title>

<style>
html, body {
    margin: 0;
    padding: 0;
    background: transparent;
    font-family: Roboto, Arial, sans-serif;
    color: #e6f2ff;
}

/* Zentrierung für Direktaufruf */
.page-wrapper {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ⭐ DER EINZIGE KASTEN */
.tile {
    background: linear-gradient(180deg, #0b1a2e, #060d18);
    border: 1px solid rgba(80,160,255,0.35);
    border-radius: 18px;
    padding: 28px 32px;

    width: 600px;
    max-width: 90vw;

    box-shadow:
        0 0 30px rgba(0,157,255,0.45),
        inset 0 0 20px rgba(0,157,255,0.18);
}

/* Titel */
.tile h1 {
    margin: 0 0 18px 0;
    text-align: center;
    color: #6bd4ff;
    text-shadow: 0 0 8px #00b7ff;
}

/* Regel-Liste */
.rule-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.rule-list li {
    padding: 12px 0;
    font-size: 16px;
    color: #e6f2ff;
    border-bottom: 1px solid #2a3b55;
}

.rule-list li:last-child {
    border-bottom: none;
}
</style>
</head>

<body>

<div class="page-wrapper">
    <div class="tile">
        <h1><?= htmlspecialchars($data["title"]) ?></h1>

        <ul class="rule-list">
            <?php foreach ($data["rules"] as $rule): ?>
                <li><?= htmlspecialchars($rule) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<script>
(function () {
  function sendHeight() {
    const height = document.body.scrollHeight;
    parent.postMessage(
      { type: "rulesHeight", height: height },
      "*"
    );
  }

  window.addEventListener("load", sendHeight);
  window.addEventListener("resize", sendHeight);
})();
</script>

</body>
</html>
