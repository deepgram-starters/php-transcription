<?php

// Increasing post_max_size and upload_max_filesize
ini_set('post_max_size', '160M');

require_once('vendor/autoload.php');


// index.php

$uri = $_SERVER['REQUEST_URI'];

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$DG_KEY = $_ENV['deepgram_api_key'];

$client = new \GuzzleHttp\Client();

if ($uri == "/") {
    $uri = "/index.html"; // Default to index.html if root is requested
}

function endsWith($haystack, $needle) {
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }
    return (substr($haystack, -$length) === $needle);
}

function getMimeTypeForFile($uri) {
    if (endsWith($uri, ".html")) {
        return "text/html";
    } else if (endsWith($uri, ".css")) {
        return "text/css";
    } else if (endsWith($uri, ".svg")) {
        return "image/svg+xml";
    } else if (endsWith($uri, ".js")) {
        return "application/javascript";
    } else {
        return "text/plain";
    }
}

// Remove "/static" from the file path construction
$filePath = "./static" . $uri;

if (file_exists($filePath)) {
    $mimeType = getMimeTypeForFile($uri);
    header("Content-Type: " . $mimeType);
    readfile($filePath);
} else {
}

// Handle API requests
if (strpos($uri, "/api") === 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle FormData request here
    try {
        // Handle FormData request here
        $model = isset($_POST['model']) ? $_POST['model'] : null;
        $features = isset($_POST['features']) ? $_POST['features'] : null;
        $tier = isset($_POST['tier']) ? $_POST['tier'] : null;
        $url = isset($_POST['url']) ? $_POST['url'] : null;
        $uploadedFile = isset($_FILES['file']) ? $_FILES['file'] : null;
        

        $innerResponse = null;

        $requestUrl ='https://api.deepgram.com/v1/listen';
        $urlParams = ''; // Initialize the variable

        if (!empty($features)) {
            $featuresArray = json_decode($features, true);
            foreach ($featuresArray as $key => $value) {
                $urlParams .= '&' . urlencode($key) . '=' . ($value === true ? 'true' : urlencode($value));
            }
            $requestUrl .= '?' . ltrim($urlParams, '&');
        }


        if ($url == null && $uploadedFile !== null && $uploadedFile['error'] === UPLOAD_ERR_OK) {
            // process the file
            $uploadedFilePath = $uploadedFile['tmp_name'];
            
            // Read the raw audio from the uploaded file
            $rawAudio = file_get_contents($uploadedFilePath);

            $payload = ["RAW_BODY" => $rawAudio];
            $headers = [
                "accept" => "application/json",
                "content-type" => "audio/wave",
                "Authorization" => "Token " . $DG_KEY
            ];

            $response = $client->request('POST', $requestUrl, [
                'body' => $rawAudio,
                'headers' => $headers
            ]);

            // Decode the inner JSON response
            $innerResponse = json_decode($response->getBody()->getContents(), true);
        } else if ($url !== null){
            $response = $client->request('POST', $requestUrl, [
                'body' => '{"url": "' . $url . '"}',
                'headers' => [
                  'Authorization' => 'Token ' . $DG_KEY,
                  'accept' => 'application/json',
                  'content-type' => 'application/json',
                ],
              ]);
            // Decode the inner JSON response
            $innerResponse = json_decode($response->getBody()->getContents(), true);
        } else{
            if ($uploadedFile !== null) {
                error_log("File upload error: " . $uploadedFile['error']);
            } else {
                error_log("No file uploaded or error occurred.");
            }
        }
        
        // Send a JSON response
        // Create the outer JSON response
        $outerResponse = array(
            'model' => $model,
            'dgFeatures' => $features,
            'tier' => $tier,
            'version' => '1.0',
            'transcription' => $innerResponse
        );
        
        // Send a JSON response
        header('Content-Type: application/json');
        echo json_encode($outerResponse);
        exit();
    } catch (Exception $e) {
        header('Content-Type: application/json', true, 400); // Bad Request status code
        echo json_encode(array('error' => 'Invalid data format'));
        exit();
    }
}

?>
