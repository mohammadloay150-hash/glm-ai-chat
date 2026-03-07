<?php
header('Content-Type: application/json; charset=utf-8');

/**
 * Developed by: Planet Blind Tech team
 * Developer: Mohammad Loay
 */

$join_link = "https://t.me/mohammad_loay222";
$dev_team = "Planet Blind Tech team";

// 1. القائمة المحدثة تماماً كما في كود الأندرويد
$models_map = [
    "Alibaba - Qwen 3 (80B)" => ["id" => "alibaba/qwen3-next-80b-a3b-instruct", "p" => "qwen-3-landing"],
    "OpenAI - GPT-4o" => ["id" => "openai/gpt-4o", "p" => "gpt-4o-landing"],
    "DeepSeek - V3.2 Exp" => ["id" => "deepseek/deepseek-non-thinking-v3.2-exp", "p" => "deepseek-v-3-2-landing"],
    "Anthropic - Claude Haiku 4.5" => ["id" => "claude-haiku-4-5-20251001", "p" => "claude-haiku-4-5-landing"]
];

function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

$uri = $_SERVER['REQUEST_URI'];

// مسار الموديلات
if (strpos($uri, '/models') !== false) {
    echo json_encode([
        "status" => "success", 
        "code" => 200,
        "available_models" => array_keys($models_map), 
        "dev" => $dev_team, 
        "join" => $join_link
    ]);
    exit;
}

// مسار الدردشة الرئيسي
if (strpos($uri, '/chat') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    $q = $_GET['q'] ?? $_GET['question'] ?? $_GET['prompt'] ?? $input['q'] ?? null;
    $user_model = $input['model'] ?? $_GET['model'] ?? null;

    // منطق الاختيار العشوائي
    $model_key = (isset($models_map[$user_model])) ? $user_model : array_keys($models_map)[array_rand(array_keys($models_map))];
    $is_random = !isset($models_map[$user_model]);

    if (!$q) {
        echo json_encode(["status" => "error", "code" => 400, "message" => "Input q is required.", "dev" => $dev_team, "join" => $join_link]);
        exit;
    }

    $payload = json_encode([
        "chatId" => generate_uuid(),
        "model" => $models_map[$model_key]['id'],
        "messages" => [["id" => generate_uuid(), "role" => "user", "content" => $q]],
        "personaId" => $models_map[$model_key]['p'],
        "stream" => true,
        "max_tokens" => 4000,
        "temperature" => 0.5
    ]);

    $ch = curl_init("https://api.overchat.ai/v1/chat/completions");
    $full_reply = "";

    $write_func = function($ch, $data) use (&$full_reply) {
        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            if (strpos($line, 'data: {') === 0) {
                $json_str = substr($line, 6);
                $decoded = json_decode($json_str, true);
                if (isset($decoded['choices'][0]['delta']['content'])) {
                    $full_reply .= $decoded['choices'][0]['delta']['content'];
                }
            }
        }
        return strlen($data);
    };

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_WRITEFUNCTION => $write_func,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "x-device-uuid: " . generate_uuid(),
            "x-device-version: 1.0.44",
            "x-device-platform: web",
            "User-Agent: Mozilla/5.0 (Linux; Android 10)",
            "Origin: https://overchat.ai",
            "Referer: https://overchat.ai/"
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 120
    ]);

    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // فرض عرض الكود 200 للمستخدم في حالة النجاح (حتى لو كان الأصلي 201)
    if ($http_code == 200 || $http_code == 201) {
        echo json_encode([
            "status" => "success",
            "code" => 200, 
            "model_used" => $model_key,
            "selection_mode" => $is_random ? "Random" : "Manual",
            "response" => trim(str_replace("\\n", "\n", $full_reply)),
            "dev" => $dev_team,
            "join" => $join_link
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "code" => $http_code,
            "message" => "API Connection Error",
            "dev" => $dev_team,
            "join" => $join_link
        ]);
    }
    exit;
}

echo json_encode(["message" => "Welcome to PBT AI API", "code" => 200, "dev" => $dev_team, "join" => $join_link]);
