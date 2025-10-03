<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Function to retrieve location and ref_id from the referer URL.
 */
function getLocationAndReferralIdFromReferer() {
    $refererUrl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

    // Parse the referer URL
    $urlPath = parse_url($refererUrl, PHP_URL_PATH);
    $queryParams = [];
    parse_str(parse_url($refererUrl, PHP_URL_QUERY), $queryParams); // Parse the query string

    // Extract path segments
    $pathSegments = explode('/', trim($urlPath, '/'));

    // Extract two segments: the region and the specific location
    if (count($pathSegments) >= 4) {
        $region = $pathSegments[1]; // Assuming the region is the third segment
        $specificLocation = $pathSegments[2]; // Assuming the specific location is the fourth segment
        $smsContestLocation = $region . '/' . $specificLocation;
    } else {
        $smsContestLocation = 'default-location'; // Fallback value
    }

    // Extract ref_id from the query parameters
    $referralId = isset($queryParams['ref_id']) ? trim($queryParams['ref_id']) : null;

    return [
        'location'   => $smsContestLocation,
        'referralId' => $referralId
    ];
}

// Call the function to get location and referralId
$refererData = getLocationAndReferralIdFromReferer();
$sms_contest_location = $refererData['location'];
$referralId = $refererData['referralId'];

// Output location and referralId for debugging (will appear in the browser console)
echo "<script>console.log(" . json_encode([
    'message'    => 'Location and referralId retrieved from referer URL',
    'location'   => $sms_contest_location,
    'referralId' => $referralId
]) . ");</script>";

/**
 * Function to retrieve user data from the OneSignal API.
 */
function getUserViaAPI($externalId) {
    $apiKey = '';
    $appId  = '';

    $endpoint = 'https://api.onesignal.com/apps/' . $appId . '/users/by/external_id/' . urlencode($externalId);
    $headers = [
        'Content-Type: application/json',
        'Authorization: Basic ' . $apiKey,
        'Accept: application/json'
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return [false, null, 'error'];
    }

    $responseData = json_decode($response, true);

    if (isset($responseData['errors']) && in_array('User not found', $responseData['errors'])) {
        return [false, null, 'new'];
    }

    $sessionCount = isset($responseData['subscriptions'][0]['session_count'])
                    ? $responseData['subscriptions'][0]['session_count']
                    : 0;
    $subscription = $responseData['subscriptions'][0] ?? null;

    if ($subscription) {
        if ($subscription['type'] === 'email') {
            return [$sessionCount, $subscription['id'], 'email'];
        } else if ($subscription['type'] === 'SMS') {
            return [$sessionCount, $subscription['id'], 'SMS'];
        }
    }

    return [$sessionCount, null, 'existing'];
}

/**
 * Function to create or update a user via the OneSignal API.
 */
function createOrUpdateUserViaAPI($externalId, $phoneNumber, $sessionCount, $sms_contest_location, $subscriptionId = null, $subscriptionType = 'new', $firstName = '', $lastName = '', $contest_keyword = '') {
    $apiKey = '';
    $appId  = '';

    // Always ensure SMS subscription is created
    $smsSubscription = [
        'type'    => 'SMS',
        'token'   => $phoneNumber,
        'enabled' => true,
    ];

    // Prepare the payload with user properties and subscription data.
    $onesignal_payload = [
        "properties" => [
            "tags" => [
                'phone_number'       => $phoneNumber,
                'sms_contest_location' => $sms_contest_location,
                'first_name'         => $firstName,
                'last_name'          => $lastName,
                'contest_keyword'    => $contest_keyword
            ]
        ],
        "identity" => [
            "external_id" => $externalId
        ],
        'subscriptions' => [$smsSubscription],
    ];

    // Set the referral quantity tag to 1.
    $onesignal_payload["tags"]["referral_qty"] = 1;
    $endpoint = 'https://api.onesignal.com/apps/' . $appId . '/users';
    $method = 'POST';

    $payload = json_encode($onesignal_payload);

    $headers = [
        'Content-Type: application/json',
        'Authorization: Basic ' . $apiKey,
        'Accept: application/json'
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        echo "<script>console.log(" . json_encode(['message' => 'cURL Error', 'error' => curl_error($ch)]) . ");</script>";
    } else {
        echo "<script>console.log(" . json_encode(['message' => 'OneSignal response code', 'code' => $httpCode]) . ");</script>";
        echo "<script>console.log(" . json_encode(['message' => 'OneSignal response', 'response' => $response]) . ");</script>";

        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "<script>console.log(" . json_encode(['message' => 'Error decoding JSON response', 'error' => json_last_error_msg()]) . ");</script>";
        } else if (isset($responseData['errors'])) {
            echo "<script>console.log(" . json_encode(['message' => 'Error in OneSignal response', 'errors' => $responseData['errors']]) . ");</script>";
        } else {
            echo "New user with SMS subscription created successfully.<br>";
        }
    }

    curl_close($ch);
}

/**
 * Function to increment the referral quantity tag for a referrer.
 */
function incrementReferralQty($referralId) {
    // Retrieve user information based on the referral ID
    list($sessionCount, $subscriptionId, $subscriptionType) = getUserViaAPI($referralId);

    if ($subscriptionType === 'new') {
        // If the user is new, create the user with an initial referral quantity of 1.
        createOrUpdateUserViaAPI($referralId, '', 0, '', null, 'new');
    } else {
        // User exists—fetch current data to obtain the referral quantity.
        $apiKey = '';
        $appId  = '';

        $endpoint = 'https://api.onesignal.com/apps/' . $appId . '/users/by/external_id/' . urlencode($referralId);
        $headers = [
            'Content-Type: application/json',
            'Authorization: Basic ' . $apiKey,
            'Accept: application/json'
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return;
        }

        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return;
        }

        // Check the existing referral quantity or initialize it to 0 if not set.
        $referralQty = isset($responseData['properties']['tags']['referral_qty']) ? (int)$responseData['properties']['tags']['referral_qty'] : 0;
        $newReferralQty = $referralQty + 1;

        // Prepare the payload with updated referral_qty.
        $onesignal_payload = [
            "properties" => [
                "tags" => [
                    "referral_qty" => $newReferralQty
                ]
            ],
            "identity" => [
                "external_id" => $referralId
            ],
            "subscriptions" => [
                [
                    "type"    => "SMS",
                    "token"   => $referralId, // Assuming phone number is used as token
                    "enabled" => true
                ]
            ]
        ];

        $payload = json_encode($onesignal_payload);

        // Set up the POST request to update the user's referral_qty.
        $endpoint = 'https://api.onesignal.com/apps/' . $appId . '/users';
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            // Optional: handle cURL error
        } else {
            $responseData = json_decode($response, true);
        }

        curl_close($ch);
    }
}

// --------------------------------------------------------------------
// Main logic to process the user data.
// --------------------------------------------------------------------
if (isset($_POST['externalId']) && isset($_POST['phone_number']) && isset($_POST['first_name']) && isset($_POST['last_name'])) {

    // Check if the contest_keyword is provided and nonempty.
    if (!isset($_POST['contest_keyword']) || trim($_POST['contest_keyword']) === '') {
         echo "No contests active, come back next time!";
         exit;
    }
    
    $contest_keyword = $_POST['contest_keyword'];
    $externalId      = '+1' . preg_replace('/\s+/', '', $_POST['externalId']);
    $phoneNumber     = '+1' . preg_replace('/\s+/', '', $_POST['phone_number']);
    $firstName       = $_POST['first_name'];
    $lastName        = $_POST['last_name'];

    // If a referral exists, format it with a leading "+"
    if ($referralId) {
        $referralId = '+' . $referralId;
    }
    
    // File path for storing referral submissions data
    $filePath = 'referral_submissions.json';
    list($sessionCount, $subscriptionId, $subscriptionType) = getUserViaAPI($externalId);
    createOrUpdateUserViaAPI($externalId, $phoneNumber, $sessionCount, $sms_contest_location, $subscriptionId, $subscriptionType, $firstName, $lastName, $contest_keyword);
} else {
    echo "Please provide externalId, phone_number, first_name, and last_name parameters.<br>";
}

// --------------------------------------------------------------------
// Process referral submission if a referral ID is present.
// --------------------------------------------------------------------
if ($referralId) {
    $ipAddress = $_SERVER['REMOTE_ADDR']; // Get the user's IP address

    // Load existing submissions from the JSON file
    $submissions = loadSubmissions($filePath);

    if (isUniqueSubmissionByIP($referralId, $ipAddress, $submissions)) {
        incrementReferralQty($referralId); // Increment the referral count
        recordSubmissionWithIP($referralId, $ipAddress, $submissions);
        saveSubmissions($filePath, $submissions); // Save updated submissions
        echo "<script>console.log('Referral recorded successfully.');</script>";
    } else {
        // Prevent duplicate referral submissions from the same IP address
        echo "<script>
                console.log('You have already submitted a referral from this IP address.');
                document.addEventListener('DOMContentLoaded', function() {
                    var form = document.querySelector('form');
                    if (form) {
                        form.addEventListener('submit', function(event) {
                            event.preventDefault();
                            alert('You have already submitted a referral from this IP address.');
                        });
                    }
                });
              </script>";
    }
} else {
    echo "<script>console.log('Referral ID is not set or invalid.');</script>";
}

/**
 * Function to load submissions from a JSON file.
 */
function loadSubmissions($filePath) {
    if (file_exists($filePath)) {
        $jsonData = file_get_contents($filePath);
        return json_decode($jsonData, true) ?: [];
    }
    return [];
}

/**
 * Function to save submissions to a JSON file.
 */
function saveSubmissions($filePath, $data) {
    if (file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT))) {
        echo "<script>console.log('Data saved successfully to the JSON file.');</script>";
    } else {
        echo "<script>console.log('Failed to save data to the JSON file.');</script>";
    }
}

/**
 * Function to check if a submission is unique by IP address.
 */
function isUniqueSubmissionByIP($referralId, $ipAddress, $submissions) {
    return !isset($submissions[$referralId]) || !in_array($ipAddress, $submissions[$referralId]);
}

/**
 * Function to record a submission by IP address.
 */
function recordSubmissionWithIP($referralId, $ipAddress, &$submissions) {
    if (!isset($submissions[$referralId])) {
        $submissions[$referralId] = [];
    }
    $submissions[$referralId][] = $ipAddress;
}
?>