<?php
header('Content-Type: application/json; charset=utf-8');

$join_link = "https://t.me/mohammad_loay222";
$dev_team = "Planet Blind Tech team";

$ai_models = [
    ["Anthropic - Claude Haiku 4.5", "claude-haiku-4-5-20251001", "claude-haiku-4-5-landing"],
    ["Alibaba - Qwen 3 (80B)", "alibaba/qwen3-next-80b-a3b-instruct", "qwen-3-landing"],
    ["OpenAI - GPT-4o", "openai/gpt-4o", "gpt-4o-landing"],
    ["DeepSeek - V3.2 Exp", "deepseek/deepseek-non-thinking-v3.2-exp", "deepseek-v-3-2-landing"]
];

function clean_text($text) {
    // إزالة الإيموجي والرموز الملونة
    $text = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $text);
    // إزالة علامات المارك داون (النجوم، المربعات، الروابط، الترويسات)
    $text = preg_replace('/(\*|_|`|#|\[|\]|\(|\)|>)/', '', $text);
    return trim($text);
}

function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

$uri = $_SERVER['REQUEST_URI'];

if (strpos($uri, '/chat') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    $q = $_GET['q'] ?? $_GET['question'] ?? $input['q'] ?? null;
    $user_model_name = $_GET['model'] ?? $input['model'] ?? null;
    
    // ميزة التنظيف اختيارية تماماً (عبر الرابط أو الـ JSON)
    $is_clean_requested = (isset($_GET['clean']) && $_GET['clean'] === 'true') || (isset($input['clean']) && $input['clean'] === true);

    $current_model = null;
    $selection_info = "Manual Selection";

    if ($user_model_name) {
        foreach ($ai_models as $m) {
            if (stripos($m[0], $user_model_name) !== false) {
                $current_model = $m;
                break;
            }
        }
    }

    if (!$current_model) {
        $current_model = $ai_models[0]; 
        $selection_info = $user_model_name 
            ? "Note: The API is using the default model because the provided model name is invalid." 
            : "Note: The API is using the default model because no specific model was provided in the request.";
    }

    if (!$q) {
        echo json_encode(["status" => "error", "code" => 400, "message" => "Question (q) is required"], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    $payload = json_encode([
        "chatId" => generate_uuid(),
        "model" => $current_model[1],
        "messages" => [["id" => generate_uuid(), "role" => "user", "content" => $q]],
        "personaId" => $current_model[2],
        "stream" => true,
        "max_tokens" => 4000,
        "temperature" => 0.5
    ]);

    $ch = curl_init("https://api.overchat.ai/v1/chat/completions");
    $final_answer = "";

    $write_callback = function($ch, $data) use (&$final_answer) {
        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            if (strpos($line, 'data: {') === 0) {
                $json_str = substr($line, 6);
                $decoded = json_decode($json_str, true);
                if (isset($decoded['choices'][0]['delta']['content'])) {
                    $final_answer .= $decoded['choices'][0]['delta']['content'];
                }
            }
        }
        return strlen($data);
    };

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_WRITEFUNCTION => $write_callback,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json", "x-device-uuid: ".generate_uuid(), "x-device-version: 1.0.44", "x-device-platform: web", "User-Agent: Mozilla/5.0 (Linux; Android 10)"],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 120
    ]);

    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200 || $http_code == 201) {
        $raw_text = trim($final_answer);
        
        // إذا طلب المستخدم التنظيف، نظف النص بصمت
        $response_text = $is_clean_requested ? clean_text($raw_text) : $raw_text;

        $response_data = [
            "status" => "success",
            "code" => 200,
            "model_used" => $current_model[0],
            "selection_mode" => $selection_info,
            "response" => $response_text,
            "dev" => $dev_team,
            "join" => $join_link
        ];
    } else {
        $response_data = ["status" => "error", "code" => $http_code, "message" => "API connection failed", "dev" => $dev_team];
    }
    echo json_encode($response_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
