<?php

// Shared secret from the bot installation for secure communication
$secret = '170be4645a7052cd190e2344998a56ed4eb94a5971c5a7e0fd5afac503ba8a8c';

// OpenAI API key
$openaiApiKey = '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';

// 1. Receive the webhook
// Retrieve and decode the incoming JSON payload from the webhook
$data = json_decode(file_get_contents('php://input'), true);

// 2. Verify the signature
// Get the signature and random value sent in the HTTP headers
$signature = $_SERVER['HTTP_X_NEXTCLOUD_TALK_SIGNATURE'] ?? '';
$random = $_SERVER['HTTP_X_NEXTCLOUD_TALK_RANDOM'] ?? '';

// Generate a hash-based message authentication code (HMAC) using the random value and the payload
$generatedDigest = hash_hmac('sha256', $random . file_get_contents('php://input'), $secret);

// Compare the generated digest with the signature provided in the request
if (!hash_equals($generatedDigest, strtolower($signature))) {
    // If the signature is invalid, respond with HTTP 401 Unauthorized and terminate
    http_response_code(401);
    exit;
}

// 3. Extract the message
// Retrieve the message content from the payload
$message = $data['object']['content'];

// 4. Generate a reply using OpenAI 
function generateOpenAiResponse($message, $apiKey)
{
    $url = "https://api.openai.com/v1/chat/completions";
    $postData = [
        "model" => "gpt-4o-mini",
        "messages" => [
            [
                "role" => "system",
                "content" => "You are a digital assistant for digital campus of multiple universities. Answer student questions based on this question and answer example:\n\nQ: Can I apply for courses offered by my own University? For example, if I am a student in Cagliari can I take courses offered at the University of Cagliari?\nA: It depends on the specific course. You will find this information in the prerequisites. Please take a look at them before completing your application. Otherwise, your application might be rejected.\nQ: Can I acquire ECTS by doing a course or a program offered in the Course Catalogue?\nA: Participating in a learning opportunity will provide you with an EDUC Certificate, Work Load indicating the equivalence in ECTS. EDUC courses are recognized in different ways at the home institutions. Before sending in your application, please contact your degree coordinator to know if the activity can be recognized at your university or degree program. Even if the course is not recognized with ECTS into your study program, it will add value to your competences and professional profile.\n..."
            ],
            [
                "role" => "user",
                "content" => $message
            ]
        ],
        "temperature" => 1,
        "max_tokens" => 2048,
        "top_p" => 1,
        "frequency_penalty" => 0,
        "presence_penalty" => 0
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        return "Error contacting OpenAI API.";
    }

    $responseData = json_decode($response, true);
    return $responseData['choices'][0]['message']['content'] ?? "No response from OpenAI.";
}

// Call the OpenAI API to generate a response
$openAiResponse = generateOpenAiResponse($message, $openaiApiKey);

// 5. Send a reply to the chat
// Extract the chat room token from the webhook data
$token = $data['target']['id'];

// Define the API URL for sending a bot message to the chat room
$apiUrl = 'https://nc-test.wunderbluete.org/ocs/v2.php/apps/spreed/api/v1/bot/' . $token . '/message';

// Prepare the request body with the message, a unique reference ID, and the ID of the original message
$requestBody = [
    'message' => trim($openAiResponse), // Use the OpenAI-generated response
    'referenceId' => sha1($random), // A unique reference ID for tracking
    'replyTo' => (int) $data['object']['id'], // ID of the original message being replied to
];

// Convert the request body to a JSON string
$jsonBody = json_encode($requestBody, JSON_THROW_ON_ERROR);

// Generate a new random value for signing the reply
//$replyRandom = bin2hex(random_bytes(32)); // changed from 32

// Create a signature for the reply message using HMAC
$replySignature = hash_hmac('sha256', $random . $requestBody['message'], $secret);

// Initialize a cURL session to send the reply via the API
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Set HTTP headers for the API request, including content type and the signature
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json', // Indicate that the request body is JSON
    'OCS-APIREQUEST: true', // Required header for Nextcloud API requests
    'X-Nextcloud-Talk-Bot-Random: ' . $replyRandom,  // The random value used in the signature
    'X-Nextcloud-Talk-Bot-Signature: ' . $replySignature, // The generated signature for the response
));

// Execute the API request and store the response
$response = curl_exec($ch);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
// Close the cURL session
curl_close($ch);

// Optional: Log or handle the response for debugging purposes
if ($httpCode !== 201) {
    error_log("Nextcloud Talk API Error: " . $response);
    error_log("Response Code: " . $httpCode);
    error_log("Random: " . $random);
    error_log("Signature: " . $replySignature);
}

?>
