<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] === 'application/json') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['host'])) {
        if (preg_match('/^(https?:\/\/|)([\da-f]+-[a-z0-9-]+)-00-/', $data['host'], $m)) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://replit.com/replid/{$m[2]}?from=replit.dev",
                CURLOPT_HEADER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY => true,
            ]);
            curl_exec($ch);
            $h = curl_getinfo($ch);
            curl_close($ch);
            echo json_encode(isset($h['redirect_url']) ? ['final_url' => $h['redirect_url']] : ['error' => 'Couldn\'t extract redirecturi'], JSON_UNESCAPED_SLASHES);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid Host']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => "Please provide the 'host' param in the format of this example: \"http(s)://0.0.replit.dev:0000/\""]);
    }
} else {
    http_response_code(501);
    echo json_encode(['error' => 'Not Implemented']);
}
?>
