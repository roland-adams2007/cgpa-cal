<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'vendor/autoload.php';
require_once __DIR__ . '/vendor/autoload.php';
use \TCPDF;

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$googleApiKey = $_ENV['GOOGLE_API_KEY'];



$api_key = $googleApiKey; 

$models = [
    'gemini-1.5-pro' => 'Primary education model with deep reasoning',
    'gemini-1.5-flash' => 'Fast responses for quick educational queries',
    'gemini-1.5-pro-latest' => 'Latest version with improved educational content',
    'gemini-ultra' => 'Advanced model for complex academic topics',
    'gemini-pro' => 'Balanced model for general educational use',
    'gemini-resource-creator' => 'Specialized model for generating educational resources and tips'
];

$cache_dir = __DIR__ . '/cache';
$cache_expiry = 3600;
if (!file_exists($cache_dir)) {
    mkdir($cache_dir, 0700, true);
}

function cleanOldCache($cache_dir, $max_age = 86400) {
    foreach (glob($cache_dir . '/*.json') as $file) {
        if (time() - filemtime($file) > $max_age) {
            @unlink($file);
        }
    }
}
cleanOldCache($cache_dir);

$rate_limit_file = $cache_dir . '/rate_limits.json';
$rate_limits = file_exists($rate_limit_file) ? json_decode(file_get_contents($rate_limit_file), true) : [];
$current_time = time();
foreach ($rate_limits as $ip => $data) {
    if ($current_time - $data['timestamp'] > 3600) {
        unset($rate_limits[$ip]);
    }
}
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$hourly_limit = 10;

$prompt = isset($_POST['question']) ? trim($_POST['question']) : '';
$context = isset($_POST['context']) ? trim($_POST['context']) : '';
$education_level = 'university';

if (empty($prompt)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Question cannot be empty']);
    exit;
}

$valid_education_levels = ['elementary', 'middle_school', 'high_school', 'university', 'professional'];
$education_level = in_array($education_level, $valid_education_levels) ? $education_level : 'university';

$prompt = htmlspecialchars($prompt, ENT_QUOTES, 'UTF-8');
$context = htmlspecialchars($context, ENT_QUOTES, 'UTF-8');

$normalized_prompt = strtolower(trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $prompt)));
$cache_key = hash('sha256', $normalized_prompt . '|' . $context . '|' . $education_level);
$cache_file = $cache_dir . '/' . $cache_key . '.json';

if (file_exists($cache_file) && is_readable($cache_file) && (time() - filemtime($cache_file) < $cache_expiry)) {
    error_log("Cache hit for key: $cache_key");
    header('Content-Type: application/json');
    $cached_response = @file_get_contents($cache_file);
    if ($cached_response !== false) {
        $response_data = json_decode($cached_response, true);
        $response_data['cached'] = true;
        echo json_encode($response_data);
        exit;
    }
}
error_log("Cache miss for key: $cache_key");

if (isset($rate_limits[$client_ip]) && $rate_limits[$client_ip]['count'] >= $hourly_limit) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Rate limit exceeded. Please try again later.']);
    exit;
}
$rate_limits[$client_ip] = [
    'count' => ($rate_limits[$client_ip]['count'] ?? 0) + 1,
    'timestamp' => $current_time
];
if (is_writable($cache_dir)) {
    file_put_contents($rate_limit_file, json_encode($rate_limits));
}

function prepareRequestData($context, $prompt, $education_level) {
    $enhanced_prompt = "Respond as an educational assistant for {$education_level} level, tailored for Nigerian students. ";
    $enhanced_prompt .= "Provide 3-5 specific study tips relevant to the question, considering common academic challenges in Nigeria (e.g., limited resources, exam pressure). ";
    $enhanced_prompt .= "Include at least one relevant YouTube video link from reputable educational channels (e.g., channels covering WAEC, JAMB, or university topics). ";
    $enhanced_prompt .= "Format study tips as a numbered list with [Tip: <tip>] and YouTube links as [YouTube: <title> | <url>]. ";
    $enhanced_prompt .= "Optionally, suggest a downloadable resource (e.g., study guide) with [Resource: <description> | <content>]. ";
    $enhanced_prompt .= "Keep the response concise, under 200 words. ";
    $enhanced_prompt .= "Context: {$context}. Question: {$prompt}";
    
    return json_encode([
        "contents" => [
            "parts" => [
                ["text" => $enhanced_prompt]
            ]
        ],
        "generationConfig" => [
            "maxOutputTokens" => 1000,
            "temperature" => 0.5,
            "topP" => 0.95,
            "topK" => 40
        ],
        "safetySettings" => [
            [
                "category" => "HARM_CATEGORY_DANGEROUS_CONTENT",
                "threshold" => "BLOCK_ONLY_HIGH"
            ],
            [
                "category" => "HARM_CATEGORY_HARASSMENT",
                "threshold" => "BLOCK_MEDIUM_AND_ABOVE"
            ]
        ]
    ]);
}

function generatePDFResource($description, $content) {
    $pdf = new TCPDF();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('GradeGenie.net');
    $pdf->SetTitle($description);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Write(0, $content);
    $filename = 'resources/' . uniqid('resource_') . '.pdf';
    if (!file_exists('resources')) {
        mkdir('resources', 0700, true);
    }
    $pdf->Output(__DIR__ . '/' . $filename, 'F');
    return $filename;
}

function extractTipsAndLinks($text) {
    $tips = [];
    $youtube_links = [];
    $resources = [];
    
    preg_match_all('/\[Tip: (.*?)\]/', $text, $tip_matches);
    foreach ($tip_matches[1] as $tip) {
        $tips[] = $tip;
        $text = str_replace("[Tip: $tip]", '', $text);
    }
    
    preg_match_all('/\[YouTube: (.*?)\s*\|\s*(.*?)\]/', $text, $youtube_matches);
    for ($i = 0; $i < count($youtube_matches[0]); $i++) {
        $youtube_links[] = [
            'title' => $youtube_matches[1][$i],
            'url' => $youtube_matches[2][$i]
        ];
        $text = str_replace($youtube_matches[0][$i], '', $text);
    }
    
    preg_match_all('/\[Resource: (.*?)\s*\|\s*(.*?)\]/', $text, $resource_matches);
    for ($i = 0; $i < count($resource_matches[0]); $i++) {
        $description = $resource_matches[1][$i];
        $content = $resource_matches[2][$i];
        $filename = generatePDFResource($description, $content);
        $resources[] = [
            'description' => $description,
            'file' => $filename
        ];
        $text = str_replace($resource_matches[0][$i], '', $text);
    }
    
    return [$text, $tips, $youtube_links, $resources];
}

function sendGeminiRequest($model, $api_key, $data) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
    $headers = [
        "Content-Type: application/json",
        "Accept: application/json"
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch) ?: null;
    
    curl_close($ch);
    
    return [$response, $http_code, $curl_error];
}

function formatEducationalResponse($text, $education_level) {
    $text = preg_replace('/\*\*(.*?)\*\*/m', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.*?)\*/m', '<em>$1</em>', $text);
    $sections = [];
    if (preg_match_all('/##\s+(.*?)(?=##|\z)/s', $text, $matches)) {
        foreach ($matches[0] as $section) {
            $section = trim($section);
            if (!empty($section)) {
                $sections[] = $section;
            }
        }
    }
    
    return empty($sections) ? $text : [
        'original' => $text,
        'formatted' => $sections
    ];
}

$data = prepareRequestData($context, $prompt, $education_level);
$response = null;
$http_code = null;
$result = null;
$error = null;
$used_model = null;
$retry_count = 0;
$max_retries = 3;

foreach (array_keys($models) as $model) {
    $retry_count = 0;
    
    while ($retry_count < $max_retries) {
        list($response, $http_code, $curl_error) = sendGeminiRequest($model, $api_key, $data);
        
        if ($curl_error) {
            $error = "Network error: " . $curl_error;
            $retry_count++;
            sleep(pow(2, $retry_count));
            continue;
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $used_model = $model;
            break 2;
        }
        
        if (isset($result['error'])) {
            $error_code = $result['error']['code'] ?? 0;
            $error = $result['error']['message'] ?? 'Unknown error';
            
            if ($error_code == 429 || $error_code == 503) {
                $retry_count++;
                sleep(pow(2, $retry_count));
                continue;
            }
            
            break;
        }
        
        $error = 'Unexpected response format';
        break;
    }
}

header('Content-Type: application/json');

if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
    $response_text = $result['candidates'][0]['content']['parts'][0]['text'];
    list($cleaned_text, $tips, $youtube_links, $resources) = extractTipsAndLinks($response_text);
    $formatted_response = formatEducationalResponse($cleaned_text, $education_level);
    
    $response_data = [
        'text' => $cleaned_text,
        'formatted' => $formatted_response,
        'tips' => $tips,
        'youtube_links' => $youtube_links,
        'resources' => $resources,
        'model' => [
            'name' => $used_model,
            'description' => $models[$used_model] ?? ''
        ],
        'education_level' => $education_level,
        'cached' => false
    ];
    
    if (is_writable($cache_dir)) {
        @file_put_contents($cache_file, json_encode($response_data));
    }
    
    echo json_encode($response_data);
} elseif (isset($result['error'])) {
    echo json_encode(['error' => $result['error']['message']]);
} else {
    echo json_encode(['error' => $error ?? 'Unexpected response format. Please try again later.']);
}
exit;
?>