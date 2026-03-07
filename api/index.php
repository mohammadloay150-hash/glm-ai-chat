<?php
header('Content-Type: application/json; charset=utf-8');

$models_map = [
    "Qwen 3" => ["id" => "alibaba/qwen3-next-80b-a3b-instruct", "p" => "qwen-3-landing"],
    "GPT-4o" => ["id" => "openai/gpt-4o", "p" => "gpt-4o-landing"],
    "DeepSeek V3.2" => ["id" => "deepseek/deepseek-non-thinking-v3.2-exp", "p" => "deepseek-v-3-2-landing"],
    "Claude 4.5 Haiku" => ["id" => "claude-haiku-4-5-20251001", "p" => "claude-haiku-4-5-landing"]
];

function strip_markdown($text) {
    $patterns = ['/\*\*/', '/__/', '/\*/', '/_/', '/#+/', '/`/', '/~/', '/>\s/'];
    return trim(preg_replace($patterns, '', $text));
}

function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

$uri = $_SERVER['REQUEST_URI'];

if (strpos($uri, '/models') !== false) {
    echo json_encode([
        "status" => "success",
        "code" => 200,
        "available_models" => array_keys($models_map)
    ]);
    exit;
}

if (strpos($uri, '/chat') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    $q = $_GET['q'] ?? $_GET['question'] ?? $_GET['prompt'] ?? null;
    $model_key = $input['model'] ?? $_GET['model'] ?? "Qwen 3";
    $clean = isset($_GET['clean']) ? ($_GET['clean'] === 'true') : ($input['clean'] ?? false);

    if (!isset($models_map[$model_key])) {
        echo json_encode(["status" => "error", "code" => 400, "message" => "Model error: Invalid model name."]);
        exit;
    }

    $target = $models_map[$model_key];
    $messages = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($input['messages'])) {
        foreach ($input['messages'] as $m) {
            $messages[] = ["id" => generate_uuid(), "role" => $m['role'], "content" => $m['content']];
        }
    } elseif ($q) {
        $messages[] = ["id" => generate_uuid(), "role" => "user", "content" => $q];
    } else {
        echo json_encode(["status" => "error", "code" => 400, "message" => "Parameter error: Input required (q, question, or prompt)."]);
        exit;
    }

    $ch = curl_init("https://api.overchat.ai/v1/chat/completions");
    $payload = json_encode([
        "chatId" => generate_uuid(),
        "model" => $target['id'],
        "messages" => $messages,
        "personaId" => $target['p'],
        "stream" => true,
        "max_tokens" => 4000
    ]);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "x-device-uuid: " . generate_uuid(),
            "User-Agent: Mozilla/5.0 (Linux; Android 10)",
            "origin: https://overchat.ai"
        ],
        CURLOPT_TIMEOUT => 40
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || !$response) {
        echo json_encode(["status" => "error", "code" => 500, "message" => "error"]);
        exit;
    }

    $full_reply = "";
    $lines = explode("\n", $response);
    foreach ($lines as $line) {
        if (strpos($line, 'data: {') === 0) {
            $data = json_decode(substr($line, 6), true);
            $full_reply .= $data['choices'][0]['delta']['content'] ?? "";
        }
    }

    if ($clean) $full_reply = strip_markdown($full_reply);

    echo json_encode(["status" => "success", "code" => 200, "model" => $model_key, "response" => $full_reply]);
    exit;
}
?>
