<?php
header('Content-Type: application/json; charset=utf-8');

// إعدادات الفريق والروابط
$join_link = "https://t.me/mohammad_loay222";
$dev_team = "Planet Blind Tech team";

// قائمة النماذج المتاحة
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

// --- مسار الموديلات ---
if (strpos($uri, '/models') !== false) {
    echo json_encode([
        "status" => "success",
        "available_models" => array_keys($models_map),
        "dev" => $dev_team,
        "join" => $join_link
    ]);
    exit;
}

// --- مسار الدردشة ---
if (strpos($uri, '/chat') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    $q = $_GET['q'] ?? $_GET['question'] ?? $_GET['prompt'] ?? $input['q'] ?? null;
    $user_model = $input['model'] ?? $_GET['model'] ?? null;
    $clean = isset($_GET['clean']) ? ($_GET['clean'] === 'true') : ($input['clean'] ?? false);
    
    $is_random = false;
    $model_key = "";

    // منطق اختيار النموذج (محدد أو عشوائي)
    if ($user_model && isset($models_map[$user_model])) {
        $model_key = $user_model;
    } else {
        // إذا لم يختار أو أخطأ في الاسم، نختار نموذجاً عشوائياً
        $model_names = array_keys($models_map);
        $model_key = $model_names[array_rand($model_names)];
        $is_random = true;
    }

    if (!$q) {
        echo json_encode([
            "status" => "error", 
            "message" => "Input required (q, question, or prompt).", 
            "dev" => $dev_team,
            "join" => $join_link
        ]);
        exit;
    }

    $target = $models_map[$model_key];
    $messages = [];

    // دعم السياق (Messages) أو السؤال الفردي
    if (isset($input['messages']) && is_array($input['messages'])) {
        foreach ($input['messages'] as $m) {
            $messages[] = ["id" => generate_uuid(), "role" => $m['role'], "content" => $m['content']];
        }
    } else {
        $messages[] = ["id" => generate_uuid(), "role" => "user", "content" => $q];
    }

    $ch = curl_init("https://api.overchat.ai/v1/chat/completions");
    $payload = json_encode([
        "chatId" => generate_uuid(),
        "model" => $target['id'],
        "messages" => $messages,
        "personaId" => $target['p'],
        "stream" => true,
        "max_tokens" => 4000,
        "temperature" => 0.7
    ]);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "x-device-uuid: " . generate_uuid(),
            "x-device-version: 1.0.44",
            "x-device-platform: web",
            "User-Agent: Mozilla/5.0 (Linux; Android 10)",
            "origin: https://overchat.ai",
            "Referer: https://overchat.ai/"
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        echo json_encode(["status" => "error", "code" => $http_code, "message" => "Internal API Error", "dev" => $dev_team]);
        exit;
    }

    $full_reply = "";
    $lines = explode("\n", $response);
    foreach ($lines as $line) {
        if (strpos($line, 'data: {') === 0) {
            $data = json_decode(substr($line, 6), true);
            if (isset($data['choices'][0]['delta']['content'])) {
                $full_reply .= $data['choices'][0]['delta']['content'];
            }
        }
    }

    // فحص بديل إذا فشل الـ Stream
    if (empty($full_reply)) {
        preg_match_all('/"content":"(.*?)"/', $response, $matches);
        if (isset($matches[1])) {
            foreach ($matches[1] as $match) { $full_reply .= stripcslashes($match); }
        }
    }

    if ($clean) $full_reply = strip_markdown($full_reply);

    // تجهيز النتيجة النهائية
    $output = [
        "status" => "success",
        "model_used" => $model_key,
        "selection_mode" => $is_random ? "Random (No model specified or invalid name)" : "Manual",
        "response" => trim($full_reply),
        "dev" => $dev_team,
        "join" => $join_link
    ];

    echo json_encode($output);
    exit;
}

// --- الصفحة الرئيسية ---
echo json_encode([
    "message" => "Welcome to PBT AI API",
    "dev" => $dev_team,
    "join" => $join_link,
    "endpoints" => [
        "chat" => "/chat?q=your_question&model=GPT-4o",
        "models" => "/models"
    ]
]);
?>
