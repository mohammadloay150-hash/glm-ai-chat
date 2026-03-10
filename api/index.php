<?php

header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: *');

header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    exit(0);

}

class GLM_API {

    private $url = "https://chat.z.ai";

    private $apiEndpoint = "https://chat.z.ai/api/v2/chat/completions";

    private $defaultModel = "glm-4.6";

    

    private $apiKey = null;

    private $authUserId = null;

    private $models = [];

    private $modelAliases = [

        'glm-4.6' => 'GLM-4-6-API-V1',

        'glm-4.6v' => 'glm-4.6v',

        'glm-4.5' => '0727-360B-API',

        'glm-4.5-air' => '0727-106B-API',

        'glm-4.5v' => 'glm-4.5v',

        'glm-4.1v-9b-thinking' => 'GLM-4.1V-Thinking-FlashX',

        'z1-rumination' => 'deep-research',

        'z1-32b' => 'zero',

        'chatglm' => 'glm-4-flash',

        '0808-360b-dr' => '0808-360B-DR',

        'glm-4-32b' => 'glm-4-air-250414'

    ];

    private function uuidv4() {

        $data = random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); 

        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); 

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

    }

    private function createSignatureWithTimestamp($e, $t) {

        $current_time = (int) round(microtime(true) * 1000);

        $current_time_string = (string) $current_time;

        

        $t_btoa = base64_encode($t);

        $data_string = "{$e}|{$t_btoa}|{$current_time_string}";

        $time_window = floor($current_time / (5 * 60 * 1000));

        $base_key = 'key-@@@@)))()((9))-xxxx&&&%%%%%';

        $base_signature = hash_hmac('sha256', (string)$time_window, $base_key);

        $signature = hash_hmac('sha256', $data_string, $base_signature);

        return [

            "signature" => $signature,

            "timestamp" => $current_time

        ];

    }

    private function prepareAuthParams($token, $user_id) {

        $current_time = (string) round(microtime(true) * 1000);

        $request_id = $this->uuidv4();

        $basic_params = [

            "requestId" => $request_id,

            "timestamp" => $current_time,

            "user_id" => $user_id,

        ];

        $now = new DateTime('now', new DateTimeZone('UTC'));

        $local = new DateTime('now');

        $offset_mins = -1 * ($local->getOffset() / 60); 

        $additional_params = [

            "version" => "0.0.1",

            "platform" => "web",

            "token" => $token,

            "user_agent" => "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Mobile Safari/537.36",

            "language" => "en-US",

            "languages" => "en-US",

            "timezone" => "Asia/Makassar",

            "cookie_enabled" => "true",

            "screen_width" => "360",

            "screen_height" => "806",

            "screen_resolution" => "360x806",

            "viewport_height" => "714",

            "viewport_width" => "360",

            "viewport_size" => "360x714",

            "color_depth" => "24",

            "pixel_ratio" => "2",

            "current_url" => "https://chat.z.ai/c/25455c46-9de3-4689-9e0a-0f9f70c5b67e",

            "pathname" => "/c/25455c46-9de3-4689-9e0a-0f9f70c5b67e",

            "search" => "",

            "hash" => "",

            "host" => "chat.z.ai",

            "hostname" => "chat.z.ai",

            "protocol" => "https:",

            "referrer" => "",

            "title" => "Z.ai Chat - Free AI powered by GLM-4.6 & GLM-4.5",

            "timezone_offset" => (string) $offset_mins,

            "local_time" => $now->format('Y-m-d\TH:i:s.v\Z'),

            "utc_time" => $now->format('D, d M Y H:i:s \G\M\T'),

            "is_mobile" => "true",

            "is_touch" => "true",

            "max_touch_points" => "2",

            "browser_name" => "Chrome",

            "os_name" => "Android"

        ];

        $all_params = array_merge($basic_params, $additional_params);

        $url_params_string = http_build_query($all_params);

        ksort($basic_params);

        $sorted_payload_arr = [];

        foreach ($basic_params as $k => $v) {

            $sorted_payload_arr[] = "{$k},{$v}";

        }

        $sorted_payload = implode(",", $sorted_payload_arr);

        return [

            "sortedPayload" => $sorted_payload,

            "urlParams" => $url_params_string

        ];

    }

    private function getCacheFilePath() {

        return sys_get_temp_dir() . '/.glm_auth_cache.json';

    }

    private function getAuthFromCache() {

        $file = $this->getCacheFilePath();

        if (file_exists($file)) {

            $mtime = filemtime($file);

            if ((time() - $mtime) < (5 * 60)) {

                $content = file_get_contents($file);

                return json_decode($content, true);

            }

        }

        return null;

    }

    private function saveAuthToCache($data) {

        $file = $this->getCacheFilePath();

        file_put_contents($file, json_encode($data));

    }

    private function curlGet($url, $headers = []) {

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if (!empty($headers)) {

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        }

        $response = curl_exec($ch);

        curl_close($ch);

        return json_decode($response, true);

    }

    private function getModels() {

        $auth = $this->getAuthFromCache();

        if (!$auth) {

            $auth = $this->curlGet("{$this->url}/api/v1/auths/");

            if ($auth) $this->saveAuthToCache($auth);

        }

        

        $this->apiKey = $auth['token'] ?? null;

        $this->authUserId = $auth['id'] ?? null;

        if (empty($this->models)) {

            $headers = ["Authorization: Bearer {$this->apiKey}"];

            $modelsData = $this->curlGet("{$this->url}/api/models", $headers);

            $data = $modelsData['data'] ?? [];

            

            $this->modelAliases = [];

            foreach ($data as $item) {

                $name = strtolower(str_replace('任务专用', 'ChatGLM', $item['name'] ?? ''));

                $this->modelAliases[$name] = $item['id'];

            }

            $this->models = array_keys($this->modelAliases);

        }

    }

    private function getModel($modelName) {

        if (isset($this->modelAliases[$modelName])) {

            return $this->modelAliases[$modelName];

        }

        return $this->modelAliases[$this->defaultModel];

    }

    public function createCompletion($prompt, $options = []) {

        $this->getModels();

        if (!$this->apiKey) {

            throw new Exception("Failed to obtain API key");

        }

        $model_id = $this->getModel($options['model'] ?? $this->defaultModel);

        $userPrompt = trim($prompt);

        $auth_params = $this->prepareAuthParams($this->apiKey, $this->authUserId);

        $sigData = $this->createSignatureWithTimestamp($auth_params['sortedPayload'], $userPrompt);

        

        $endpoint = "{$this->apiEndpoint}?{$auth_params['urlParams']}&signature_timestamp={$sigData['timestamp']}";

        $timezone = "Asia/Makassar";

        $now = new DateTime();

        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        

        $data = [

            "stream" => true,

            "model" => $model_id,

            "messages" => [["role" => "user", "content" => $prompt]],

            "signature_prompt" => $userPrompt,

            "params" => new stdClass(),

            "features" => [

                "image_generation" => false,

                "web_search" => $options['search'] ?? false,

                "auto_web_search" => $options['search'] ?? false,

                "preview_mode" => true,

                "flags" => [],

                "enable_thinking" => $options['reasoning'] ?? false

            ],

            "variables" => [

                "{{USER_NAME}}" => $options['userName'] ?? "Guest-" . time(),

                "{{USER_LOCATION}}" => "Unknown",

                "{{CURRENT_DATETIME}}" => $now->format("Y-m-d H:i:s"),

                "{{CURRENT_DATE}}" => $now->format("Y-m-d"),

                "{{CURRENT_TIME}}" => $now->format("H:i:s"),

                "{{CURRENT_WEEKDAY}}" => $days[$now->format('w')],

                "{{CURRENT_TIMEZONE}}" => $timezone,

                "{{USER_LANGUAGE}}" => "en-US"

            ],

            "chat_id" => $options['chatId'] ?? $this->uuidv4(),

            "id" => $this->uuidv4(),

            "current_user_message_id" => $this->uuidv4(),

            "current_user_message_parent_id" => null,

            "background_tasks" => [

                "title_generation" => true,

                "tags_generation" => true

            ]

        ];

        $headers = [

            "Authorization: Bearer {$this->apiKey}",

            "X-FE-Version: prod-fe-1.0.150",

            "X-Signature: {$sigData['signature']}",

            "Content-Type: application/json",

            "Accept: text/event-stream",

            "User-Agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Mobile Safari/537.36",

            "Origin: https://chat.z.ai",

            "Referer: https://chat.z.ai/"

        ];

        // تهيئة متغيرات جمع البيانات

        $full_content = '';

        $reasoning = '';

        $main_buffer = [];

        $last_yielded_answer_length = 0;

        $answer_start_index = -1;

        $in_answer_phase = false;

        $search = [];

        $usage = null;

        $ch = curl_init($endpoint);

        curl_setopt($ch, CURLOPT_POST, true);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // دالة مخصصة لقراءة الـ Stream وتجميع النص فور وصوله بنفس منطق البايثون تماماً

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (

            &$full_content, &$reasoning, &$main_buffer, &$last_yielded_answer_length,

            &$answer_start_index, &$in_answer_phase, &$search, &$usage

        ) {

            $lines = explode("\n", $chunk);

            foreach ($lines as $line) {

                if (strpos($line, 'data: ') === 0) {

                    $jsonStr = substr($line, 6);

                    $jsonData = json_decode($jsonStr, true);

                    

                    if (isset($jsonData['type']) && $jsonData['type'] === 'chat:completion') {

                        $eventData = $jsonData['data'] ?? null;

                        if (!$eventData) continue;

                        $phase = $eventData['phase'] ?? null;

                        if (isset($eventData['usage']) && !$usage) {

                            $usage = $eventData['usage'];

                        }

                        if (isset($eventData['edit_index']) && is_int($eventData['edit_index'])) {

                            $index = $eventData['edit_index'];

                            $contentChunk = isset($eventData['edit_content']) ? mb_str_split($eventData['edit_content'], 1, 'UTF-8') : [];

                            array_splice($main_buffer, $index, count($contentChunk), $contentChunk);

                            if ($in_answer_phase && $answer_start_index >= 0 && $index >= $answer_start_index) {

                                $current_answer = implode("", array_slice($main_buffer, $answer_start_index));

                                if (mb_strlen($current_answer, 'UTF-8') > $last_yielded_answer_length) {

                                    $new_content = mb_substr($current_answer, $last_yielded_answer_length, null, 'UTF-8');

                                    $full_content .= $new_content;

                                    $last_yielded_answer_length = mb_strlen($current_answer, 'UTF-8');

                                }

                            }

                        } elseif (isset($eventData['delta_content'])) {

                            $delta = $eventData['delta_content'];

                            $contentChunk = mb_str_split($delta, 1, 'UTF-8');

                            $main_buffer = array_merge($main_buffer, $contentChunk);

                            if ($phase === 'thinking') {

                                $cleaned = preg_replace('/<details[^>]*>/', '', $delta);

                                $cleaned = preg_replace('/<\/details>/', '', $cleaned);

                                $cleaned = preg_replace('/<summary>.*?<\/summary>/s', '', $cleaned);

                                $cleaned = preg_replace('/^>\s?/m', '', $cleaned);

                                if (trim($cleaned) !== '') {

                                    $reasoning .= $cleaned;

                                }

                            } elseif ($phase === 'answer') {

                                if (!$in_answer_phase) {

                                    $in_answer_phase = true;

                                    $full_text = implode("", $main_buffer);

                                    $details_end = mb_strrpos($full_text, '</details>', 0, 'UTF-8');

                                    if ($details_end !== false) {

                                        $answer_start_index = $details_end + mb_strlen('</details>', 'UTF-8');

                                    } else {

                                        $answer_start_index = count($main_buffer) - count($contentChunk);

                                    }

                                }

                                $full_content .= $delta;

                                $last_yielded_answer_length += mb_strlen($delta, 'UTF-8');

                            }

                        }

                        if ($phase === 'done' && !empty($eventData['done'])) {

                            $full_output = implode("", $main_buffer);

                            if (preg_match('/<glm_block[^>]*>([\s\S]*?)<\/glm_block>/', $full_output, $matches)) {

                                $dt = json_decode($matches[1], true);

                                if (isset($dt['data']['browser']['search_result'])) {

                                    $search = $dt['data']['browser']['search_result'];

                                }

                            }

                        }

                    }

                }

            }

            return strlen($chunk); // أمر إلزامي لـ cURL ليعرف أنه تم معالجة الـ Chunk بنجاح

        });

        curl_exec($ch);

        curl_close($ch);

        return [

            "content" => trim($full_content),

            "reasoning" => trim($reasoning),

            "search" => $search,

            "usage" => $usage

        ];

    }

}

// ==========================================

// استقبال الطلب وتوليد الرد كـ API (JSON)

// ==========================================

$inputJSON = file_get_contents('php://input');

$inputData = json_decode($inputJSON, true) ?? [];

// قراءة الـ prompt من JSON Body أو POST أو GET

$prompt = $inputData['prompt'] ?? $_POST['prompt'] ?? $_GET['prompt'] ?? '';

if (empty($prompt)) {

    echo json_encode([

        "error" => true,

        "message" => "Please provide a 'prompt' parameter (via GET, POST, or JSON body)."

    ]);

    exit;

}

// قراءة الخصائص الإضافية

$options = [

    "model" => $inputData['model'] ?? $_POST['model'] ?? $_GET['model'] ?? "glm-4.6",

    "reasoning" => filter_var($inputData['reasoning'] ?? $_POST['reasoning'] ?? $_GET['reasoning'] ?? false, FILTER_VALIDATE_BOOLEAN),

    "search" => filter_var($inputData['search'] ?? $_POST['search'] ?? $_GET['search'] ?? false, FILTER_VALIDATE_BOOLEAN)

];

try {

    $api = new GLM_API();

    $response = $api->createCompletion($prompt, $options);

    

    echo json_encode([

        "error" => false,

        "data" => $response

    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {

    echo json_encode([

        "error" => true,

        "message" => $e->getMessage()

    ]);

}
