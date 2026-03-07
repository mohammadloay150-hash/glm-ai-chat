<?php
header('Content-Type: application/json; charset=utf-8');

$join_link = "https://t.me/mohammad_loay222";
$dev_team = "Planet Blind Tech team";

$models_map = [
    "Qwen 3" => ["id" => "alibaba/qwen3-next-80b-a3b-instruct", "p" => "qwen-3-landing"],
    "GPT-4o" => ["id" => "openai/gpt-4o", "p" => "gpt-4o-landing"],
    "DeepSeek V3.2" => ["id" => "deepseek/deepseek-non-thinking-v3.2-exp", "p" => "deepseek-v-3-2-landing"],
    "Claude 4.5 Haiku" => ["id" => "claude-haiku-4-5-20251001", "p" => "claude-haiku-4-5-landing"]
];

function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

$uri = $_SERVER['REQUEST_URI'];

if (strpos($uri, '/models') !== false) {
    echo json_encode(["status" => "success", "available_models" => array_keys($models_map), "dev" => $dev_team, "join" => $join_link]);
    exit;
}

if (strpos($uri, '/chat') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    $q = $_GET['q'] ?? $_GET['question'] ?? $_GET['prompt'] ?? $input['q'] ?? null;
    $user_model = $input['model'] ?? $_GET['model'] ?? null;
    
    $model_key = (isset($models_map[$user_model])) ? $user_model : array_keys($models_map)[array_rand(array_keys($models_map))];
    $is_random = !isset($models_map[$user_model]);

    if (!$q) {
        echo json_encode(["status" => "error", "message" => "Question is required.", "dev" => $dev_team, "join" => $join_link]);
        exit;
    }

    $payload = json_encode([
        "chatId" => generate_uuid(),
        "model" => $models_map[$model_key]['id'],
        "messages" => [["id" => generate_uuid(), "role" => "user", "content" => $q]],
        "personaId" => $models_map[$model_key]['p'],
        "stream" => true
    ]);

    $ch = curl_init("https://api.overchat.ai/v1/chat/completions");
    
    $full_reply = "";

    // هذه الوظيفة السحرية تقوم بقراءة الـ Stream حرفاً بحرف وتجميعه
    $write_function = function($ch, $data) use (&$full_reply) {
        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            if (strpos($line, 'data: ') === 0) {
                $json_part = substr($line, 6);
                if (trim($json_part) == "[DONE]") continue;
                
                $decoded = json_decode($json_part, true);
                if (isset($decoded['choices'][0]['delta']['content'])) {
                    $full_reply .= $decoded['choices'][0]['delta']['content'];
                }
            }
        }
        return strlen($data);
    };

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false, // نضعه false لأننا سنستخدم WRITEFUNCTION
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_WRITEFUNCTION => $write_function,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "x-device-version: 1.0.44",
            "x-device-platform: web",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
            "Origin: https://overchat.ai",
            "Referer: https://overchat.ai/"
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 90 // زيادة وقت الانتظار لأن الـ Stream يأخذ وقتاً
    ]);

    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (empty($full_reply) && $http_code !== 200) {
        echo json_encode(["status" => "error", "code" => $http_code, "message" => "Failed to fetch response.", "dev" => $dev_team]);
        exit;
    }

    echo json_encode([
        "status" => "success",
        "model_used" => $model_key,
        "selection_mode" => $is_random ? "Random" : "Manual",
        "response" => trim($full_reply),
        "dev" => $dev_team,
        "join" => $join_link
    ]);
    exit;
}

echo json_encode(["message" => "Welcome to PBT AI", "dev" => $dev_team, "join" => $join_link]);
