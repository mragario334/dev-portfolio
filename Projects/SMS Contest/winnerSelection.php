<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}



function retry_with_delay($callback, $retries = 2, $delay = 2) {
    for ($i = 0; $i < $retries; $i++) {
        $result = $callback();
        if ($result) {
            return $result;
        }
        sleep($delay); // Wait for a while before retrying
    }
    return false;
}

function getContestKeyword($post_id = null) {
    if (empty($post_id)) {
        if (is_admin() && isset($_GET['post'])) {
            $post_id = intval($_GET['post']);
        } elseif (!is_admin()) {
            $post_id = get_the_ID();
        }
    }

    if (empty($post_id) || !is_numeric($post_id)) {
        return '';
    }

    $args = [
        'post_type'  => 'page',
        'p'          => $post_id,
        'meta_query' => [
            ['key' => '_wp_page_template', 'value' => 'template-parts/template-contest-rules.php', 'compare' => '='],
            ['key' => 'contest_active', 'value' => '1', 'compare' => '=']
        ]
    ];

    $contest_query = new WP_Query($args);
    $contest_keyword = '';

    if ($contest_query->have_posts()) {
        while ($contest_query->have_posts()) {
            $contest_query->the_post();
            $contest_keyword = get_post_field('post_name', get_the_ID());
        }
    }
    wp_reset_postdata();

    return $contest_keyword;
}

function fetchUserDetails($externalId) {
    if (!is_string($externalId)) {
        return null; // Exit early if the input is invalid
    }

    $appId = '';
    $apiKey = '';

    $endpoint = 'https://api.onesignal.com/apps/' . $appId . '/users/by/external_id/' . urlencode($externalId);
    $headers = ['Content-Type: application/json', 'Authorization: Basic ' . $apiKey, 'Accept: application/json'];

    $callback = function () use ($endpoint, $headers) {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        return $response !== false ? json_decode($response, true) : null;
    };

    return retry_with_delay($callback, 5, 2);
}





function exportAndProcessOneSignalCSV() {
    $appId = '';
    $apiKey = '';

    $endpoint = 'https://api.onesignal.com/players/csv_export?app_id=' . $appId;
    $headers = ['Content-Type: application/json', 'Authorization: Basic ' . $apiKey, 'Accept: application/json'];
    $body = json_encode(["segment_name" => "SMS Contest"]);

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($response === false) return [];

    $responseData = json_decode($response, true);
    if (!isset($responseData['csv_file_url'])) return [];

    $callback = function () use ($responseData) {
        return @gzdecode(@file_get_contents($responseData['csv_file_url']));
    };

    $csvContent = retry_with_delay($callback, 5, 2); // Retry downloading the CSV up to 5 times with a delay
    if ($csvContent === false) return [];

    $rows = array_map('str_getcsv', explode("\n", $csvContent));
    $header = array_shift($rows);
    $externalIds = [];

    $externalIdIndex = array_search('identifier', $header);
    if ($externalIdIndex === false) return [];

    foreach ($rows as $row) {
        if (isset($row[$externalIdIndex]) && !empty($row[$externalIdIndex])) {
            $externalId = $row[$externalIdIndex];
            $userDetails = fetchUserDetails($externalId);
            $contestKeyword = $userDetails['properties']['tags']['contest_keyword'] ?? null;

            $externalIds[] = ['external_id' => $externalId, 'contest_keyword' => $contestKeyword];
        }
    }

    return $externalIds;
}

function getFinalExternalIdsArray($post_id = null) {
    $activeContestKeyword = getContestKeyword($post_id);
    $debug_logs = ['active_contest_keyword' => $activeContestKeyword];

    if (empty($activeContestKeyword)) {
        $debug_logs['error'] = 'No active contest keyword found.';
        return ['final_external_ids' => [], 'debug_logs' => $debug_logs];
    }

    $externalIds = exportAndProcessOneSignalCSV();
    $filteredExternalIds = array_filter($externalIds, fn($entry) => $entry['contest_keyword'] === $activeContestKeyword);


    $finalExternalIds = [];
    foreach ($filteredExternalIds as $entry) {
        $externalId = $entry['external_id'];
        $userDetails = fetchUserDetails($externalId);
        $referralQty = max((int)($userDetails['properties']['tags']['referral_qty'] ?? 1), 1);

        $finalExternalIds = array_merge($finalExternalIds, array_fill(0, $referralQty, $externalId));
    }

    return ['final_external_ids' => $finalExternalIds, 'debug_logs' => $debug_logs];
}

function updateContestStatusTag($externalId, $status) {
    $appId = '';
    $apiKey = '';

    $endpoint = 'https://api.onesignal.com/apps/' . $appId . '/users/by/external_id/' . urlencode($externalId);
    $headers = ['Content-Type: application/json', 'Authorization: Basic ' . $apiKey, 'Accept: application/json'];
    $body = json_encode(['properties' => ['tags' => ['contest_status' => $status]]]);

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);


    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}

function selectWinnerAndUpdateStatus($post_id = null) {
    $result = getFinalExternalIdsArray($post_id);
    $finalExternalIds = $result['final_external_ids'];
    $debug_logs = $result['debug_logs'];

    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'final_external_ids' => $finalExternalIds,
    ];
    log_to_file('Final External IDs: ' . json_encode($logData, JSON_PRETTY_PRINT));

    if (empty($finalExternalIds)) {
        return ['winner' => null, 'debug_logs' => $debug_logs];
    }

    // Select the winner
    $winnerExternalId = $finalExternalIds[array_rand($finalExternalIds)];

    // Update the OneSignal tag for winner and non-winners
    foreach ($finalExternalIds as $externalId) {
        $status = ($externalId === $winnerExternalId) ? 'winner' : 'non-winner';
        $updated = updateContestStatusTag($externalId, $status);
        if (!$updated) {
            $debug_logs['update_error'][$externalId] = "Failed to update status for $externalId";
        }
    }

    // Fetch winner details to return
    $winnerDetails = fetchWinnerDetails($winnerExternalId);
    $debug_logs['selected_winner'] = $winnerExternalId;

    return ['winner' => $winnerDetails, 'debug_logs' => $debug_logs];
}


function fetchWinnerDetails($externalId) {
    $userDetails = fetchUserDetails($externalId);
    $debug_logs = [
        'external_id' => $externalId,
        'user_details' => $userDetails,
    ];

    if ($userDetails && isset($userDetails['properties']['tags'])) {
        return [
            'external_id' => $externalId,
            'first_name' => $userDetails['properties']['tags']['first_name'] ?? '',
            'last_name' => $userDetails['properties']['tags']['last_name'] ?? '',
            'debug_logs' => $debug_logs,
        ];
    }

    $debug_logs['error'] = 'Failed to fetch user details or tags are missing.';
    return [
        'first_name' => '',
        'last_name' => '',
        'debug_logs' => $debug_logs,
    ];
}
