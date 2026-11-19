<?php
// includes/gemini.php
// Talks to the Gemini API. Never prints or logs the API key.

require_once __DIR__ . '/functions.php';

// Plain-English message for each HTTP code
function gemini_friendly_message(int $code): string
{
    switch ($code) {
        case 400:
            return 'Google rejected the request (400). The request format or settings are wrong.';
        case 403:
            return 'Key rejected or API not enabled (403). Check GEMINI_API_KEY in config.php.';
        case 404:
            return 'Model not found (404). Check GEMINI_MODEL in config.php. Use the "List models" button to see valid names.';
        case 429:
            return 'Limit reached (429). Daily limit reached, try tomorrow. Or too many requests per minute: wait and retry.';
        case 500:
        case 502:
        case 503:
        case 504:
            return 'Google server problem (' . $code . '). Try again in a few minutes.';
        default:
            return 'Unexpected response from Google (HTTP ' . $code . ').';
    }
}

// Reads Google's own error text from an error body (if any)
function gemini_error_detail(string $body): string
{
    $json = json_decode($body, true);
    if (is_array($json) && isset($json['error']['message'])) {
        return (string)$json['error']['message'];
    }
    return '';
}

// Checks a successful response and pulls out the text.
// Returns: ok (bool), text, message
function gemini_parse_response(array $json): array
{
    // The prompt itself was blocked
    if (!empty($json['promptFeedback']['blockReason'])) {
        return ['ok' => false, 'text' => '',
            'message' => 'Blocked by safety filter: ' . $json['promptFeedback']['blockReason']];
    }

    $candidate = $json['candidates'][0] ?? null;
    if (!$candidate) {
        return ['ok' => false, 'text' => '', 'message' => 'Gemini returned no answer.'];
    }

    // Join all text parts (ignore "thought" parts)
    $text = '';
    foreach ($candidate['content']['parts'] ?? [] as $part) {
        if (isset($part['text']) && empty($part['thought'])) {
            $text .= $part['text'];
        }
    }

    $finish = $candidate['finishReason'] ?? '';
    if ($finish === 'MAX_TOKENS') {
        return ['ok' => false, 'text' => $text, 'message' => 'The answer was cut off (too long). Try again.'];
    }
    if ($finish !== '' && $finish !== 'STOP') {
        return ['ok' => false, 'text' => $text, 'message' => 'Answer stopped early. Reason: ' . $finish];
    }
    if (trim($text) === '') {
        return ['ok' => false, 'text' => '', 'message' => 'Gemini returned an empty answer.'];
    }

    return ['ok' => true, 'text' => $text, 'message' => ''];
}

// Sends a prompt to Gemini. Retries 429 and 5xx errors with growing delay.
// $options (all optional): temperature, max_tokens, json (true = ask for JSON)
// Returns: ok, text, http_code, message, raw, attempts
function gemini_generate(string $prompt, array $options = []): array
{
    $result = ['ok' => false, 'text' => '', 'http_code' => 0,
               'message' => '', 'raw' => '', 'attempts' => 0];

    // Is the key set?
    if (GEMINI_API_KEY === '' || GEMINI_API_KEY === 'PASTE_YOUR_GEMINI_KEY_HERE') {
        $result['message'] = 'Gemini key is not set. Paste it into config.php.';
        app_log('ERROR', 'Gemini key not set.');
        return $result;
    }

    @set_time_limit(600); // allow time for retries

    $url  = GEMINI_BASE_URL . '/models/' . GEMINI_MODEL . ':generateContent';
    $body = ['contents' => [['parts' => [['text' => $prompt]]]]];

    $config = [];
    if (isset($options['temperature'])) { $config['temperature'] = $options['temperature']; }
    if (isset($options['max_tokens']))  { $config['maxOutputTokens'] = $options['max_tokens']; }
    if (!empty($options['json']))       { $config['responseMimeType'] = 'application/json'; }
    if ($config) { $body['generationConfig'] = $config; }

    $request = [
        'method'  => 'POST',
        'timeout' => GEMINI_TIMEOUT,
        'headers' => ['Content-Type: application/json', 'x-goog-api-key: ' . GEMINI_API_KEY],
        'body'    => json_encode($body),
    ];

    for ($attempt = 0; $attempt <= GEMINI_MAX_RETRIES; $attempt++) {
        $result['attempts'] = $attempt + 1;
        $resp = http_request($url, $request);

        // 1. Network problem (no HTTP answer at all)
        if (!$resp['ok']) {
            $result['message'] = curl_friendly_message($resp['errno'], $resp['error']);
            app_log('ERROR', 'Gemini cURL error ' . $resp['errno'] . ': ' . $resp['error']);
            return $result;
        }

        $code = $resp['http_code'];
        $result['http_code'] = $code;

        // 2. Success
        if ($code === 200) {
            $json = json_decode($resp['body'], true);
            if (!is_array($json)) {
                $result['message'] = 'Google sent a reply that is not valid JSON.';
                $result['raw'] = substr($resp['body'], 0, 2000);
                app_log('ERROR', 'Gemini 200 but invalid JSON.');
                return $result;
            }
            $parsed = gemini_parse_response($json);
            $result['ok']      = $parsed['ok'];
            $result['text']    = $parsed['text'];
            $result['message'] = $parsed['message'];
            if (!$parsed['ok']) {
                $result['raw'] = substr($resp['body'], 0, 2000);
                app_log('WARN', 'Gemini answer problem: ' . $parsed['message']);
            }
            return $result;
        }

        // 3. Error: save the details
        $result['message'] = gemini_friendly_message($code);
        $result['raw']     = substr($resp['body'], 0, 2000);
        $detail            = gemini_error_detail($resp['body']);
        app_log('ERROR', 'Gemini HTTP ' . $code . ' (model ' . GEMINI_MODEL . '): ' . $detail);

        // 4. Retry only 429 and 5xx
        $can_retry = ($code === 429 || $code >= 500);
        if ($can_retry && $attempt < GEMINI_MAX_RETRIES) {
            sleep(2 * (2 ** $attempt));   // waits 2, 4, 8 seconds
            continue;
        }
        return $result;   // 400, 403, 404 or out of retries
    }

    return $result;
}

// Asks Google which models your key can see.
// Returns: ok, models (list of names), message, raw
function gemini_list_models(): array
{
    $out = ['ok' => false, 'models' => [], 'message' => '', 'raw' => ''];

    if (GEMINI_API_KEY === '' || GEMINI_API_KEY === 'PASTE_YOUR_GEMINI_KEY_HERE') {
        $out['message'] = 'Gemini key is not set. Paste it into config.php.';
        return $out;
    }

    $resp = http_request(GEMINI_BASE_URL . '/models?pageSize=200', [
        'timeout' => 30,
        'headers' => ['x-goog-api-key: ' . GEMINI_API_KEY],
    ]);

    if (!$resp['ok']) {
        $out['message'] = curl_friendly_message($resp['errno'], $resp['error']);
        app_log('ERROR', 'ListModels cURL error ' . $resp['errno'] . ': ' . $resp['error']);
        return $out;
    }
    if ($resp['http_code'] !== 200) {
        $out['message'] = gemini_friendly_message($resp['http_code']);
        $out['raw']     = substr($resp['body'], 0, 2000);
        app_log('ERROR', 'ListModels HTTP ' . $resp['http_code']);
        return $out;
    }

    $json = json_decode($resp['body'], true);
    foreach ($json['models'] ?? [] as $m) {
        // Keep only models that can write text
        if (in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true)) {
            $out['models'][] = preg_replace('#^models/#', '', (string)$m['name']);
        }
    }
    sort($out['models']);
    $out['ok'] = true;
    return $out;
}