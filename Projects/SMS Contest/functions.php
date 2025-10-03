	<?php
function add_contest_rules_button_meta_box() {
    global $post;

    // Check if we are on the right post type and template
    if ($post && 'page' === get_post_type($post->ID)) {
        $template = get_page_template_slug($post->ID);

        // Only add the meta box if the page uses the "Contest Rules" template
        if ($template === 'template-parts/template-contest-rules.php') {
            add_meta_box(
                'contest_winner_button',          // Meta box ID
                'Select Contest Winner',          // Meta box title
                'render_contest_winner_button',   // Callback to render the meta box content
                'page',                           // Post type
                'side',                           // Location on the admin page
                'default'                         // Priority
            );
        }
    }
}
add_action('add_meta_boxes', 'add_contest_rules_button_meta_box');



function render_contest_winner_button() {
    $post_id = get_the_ID(); // Get the current post ID
    ?>
    <button id="contest-select-winner" class="button button-primary">Select Winner</button>
    <p id="contest-winner-output"></p>

    <script type="text/javascript">
 jQuery(document).ready(function ($) {
    $('#contest-select-winner').on('click', function (e) {
        e.preventDefault();
        const $button = $(this);
        const $output = $('#contest-winner-output');

        console.log("Button clicked. Starting AJAX request...");

        $output.text('Selecting winner...');
        $button.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'select_contest_winner',
                post_id: <?php echo esc_js($post_id); ?>,
            },
            success: function (response) {
                console.log("AJAX Response:", response);

                if (response.success) {
                    // Reload the page to reflect the updated status
                    location.reload();
                } else {
                    console.error("Error in response:", response.data.message);
                    $output.text(response.data.message || 'Error occurred.');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown);
                $output.text('An AJAX error occurred.');
            },
            complete: function () {
                $button.prop('disabled', false);
            }
        });
    });
});



</script>
    <?php
}

function select_contest_winner_callback() {
    try {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            throw new Exception('Invalid post ID.');
        }

        $is_active = get_field('contest_active', $post_id);
        if (!$is_active) {
            throw new Exception('Contest is not active.');
        }

        // Call winnerselection.php logic to get the winner and debug logs
        $result = selectWinnerAndUpdateStatus($post_id);
        $winnerDetails = $result['winner'];
        $debug_logs = $result['debug_logs'];

        if (empty($winnerDetails['first_name']) || empty($winnerDetails['last_name'])) {
            $debug_logs['error'] = 'Failed to fetch winner details.';
            throw new Exception('Failed to fetch winner details.');
        }

        // Prepare winner details text
        $winner_text = sprintf(
            "External_Id: %s\nFirst Name: %s\nLast Name: %s",
            $winnerDetails['external_id'],
            $winnerDetails['first_name'],
            $winnerDetails['last_name']
        );

        // Update the ACF field with the winner details using the field ID
        $update_success = update_field('winner', $winner_text, $post_id);

        if (!$update_success) {
            throw new Exception('Failed to update winner details ACF field.');
        }

        // Set the 'contest_active' field to false after selecting the winner
        $contest_active_update_success = update_field('contest_active', false, $post_id);

        if (!$contest_active_update_success) {
            throw new Exception('Failed to deactivate the contest.');
        }

        wp_send_json_success([
            'external_id' => $winnerDetails['external_id'],
            'first_name' => $winnerDetails['first_name'],
            'last_name' => $winnerDetails['last_name'],
            'debug_logs' => $debug_logs,
        ]);
    } catch (Throwable $e) {
        wp_send_json_error([
            'message' => $e->getMessage(),
            'debug_logs' => isset($debug_logs) ? $debug_logs : [],
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'stack_trace' => $e->getTraceAsString(),
        ]);
    }
}
 
ini_set('display_errors', 0);  

add_action('wp_ajax_select_contest_winner', 'select_contest_winner_callback');

/**
 * Register the Contest Locations metabox only for pages using the contest rules template.
 */
function add_contest_location_metabox() {
    global $post;

    // Check that $post exists and is a page using the correct template.
    if (
        $post instanceof WP_Post &&
        get_post_type( $post ) === 'page' &&
        get_page_template_slug( $post->ID ) === 'template-parts/template-contest-rules.php'
    ) {
        add_meta_box(
            'contest_locations_box',            // Unique ID
            'Contest Locations',                // Box title
            'render_contest_locations_metabox', // Content callback
            'page',                             // Post type
            'normal',
            'high'
        );
    }
}
add_action( 'add_meta_boxes', 'add_contest_location_metabox' );


/**
 * Render the metabox content.
 *
 * @param WP_Post $post The post object.
 */
function render_contest_locations_metabox( $post ) {
    // Add a nonce field for security.
    wp_nonce_field( 'save_contest_locations', 'contest_locations_nonce' );
    
    // Output our dynamic checkboxes.
    echo get_contest_location_checkboxes( $post->ID );
}


function get_contest_location_checkboxes( $current_post_id ) {
    // Only show if template is correct
    $template = get_page_template_slug( $current_post_id );
    if ( $template !== 'template-parts/template-contest-rules.php' ) {
        return ''; // Don't output anything
    }

    $output = '<div id="cd-location-wrapper">';
    $current_saved = get_post_meta( $current_post_id, 'contest_location', true );
    $locations_parent = get_page_by_path( 'locations' );
    if ( ! $locations_parent ) return '<p>No main Locations page found.</p>';

    $parent_id = $locations_parent->ID;
    $states = get_pages([
        'parent' => $parent_id,
        'post_type' => 'page',
        'post_status' => 'publish'
    ]);
    if ( empty( $states ) ) return '<p>No state pages found.</p>';

    // Get active locations from other contests
    $contest_query = new WP_Query([
        'post_type' => 'page',
        'posts_per_page' => -1,
        'post__not_in' => [ $current_post_id ],
        'meta_key' => '_wp_page_template',
        'meta_value' => 'template-parts/template-contest-rules.php'
    ]);
    $active_locations = [];
    if ( $contest_query->have_posts() ) {
        while ( $contest_query->have_posts() ) {
            $contest_query->the_post();
            $active = get_post_meta( get_the_ID(), 'contest_active', true );
            $loc_raw = get_post_meta( get_the_ID(), 'contest_location', true );
            if ( $active && $loc_raw ) {
                $loc_array = array_filter( explode( '|', trim( $loc_raw, '|' ) ) );
                $active_locations = array_merge( $active_locations, $loc_array );
            }
        }
    }
    wp_reset_postdata();
    $active_locations = array_unique( $active_locations );

    // Output per state
    foreach ( $states as $state ) {
        $state_name = get_the_title( $state );
        $state_slug = sanitize_title( $state_name );

        // Get child locations
        $children = get_pages([
            'parent' => $state->ID,
            'post_type' => 'page',
            'post_status' => 'publish'
        ]);

        $locations = array_filter($children, function($child) {
            return stripos( get_page_template_slug( $child->ID ), 'location-sub-inner' ) !== false;
        });

        if ( empty( $locations ) ) continue;

        // Add group checkbox for Naples/NOLA
		if ( strtolower( $state_name ) === 'florida' ) {
			$output .= '<h3 style="margin-top:20px;">
				<label style="font-weight:bold;">
					<input type="checkbox" class="cd-master-checkbox" data-group="florida"> Naples
				</label>
			</h3>';
		} elseif ( strtolower( $state_name ) === 'louisiana' ) {
			$output .= '<h3 style="margin-top:20px;">
				<label style="font-weight:bold;">
					<input type="checkbox" class="cd-master-checkbox" data-group="louisiana"> NOLA
				</label>
			</h3>';
		}
		
		

        foreach ( $locations as $loc ) {
            $loc_title = get_the_title( $loc );
			$clean_title = preg_replace('/,?\s*(Florida|Louisiana)$/i', '', $loc_title);
            $checked = ( $current_saved && strpos( $current_saved, '|' . $loc_title . '|' ) !== false ) ? ' checked' : '';
            $disabled = ( in_array( $loc_title, $active_locations ) && ( ! $current_saved || strpos( $current_saved, '|' . $loc_title . '|' ) === false ) ) ? ' disabled' : '';

            $output .= '<label style="display:block; margin-bottom:5px;">';
            $output .= '<input type="checkbox" name="contest_locations[]" value="' . esc_attr( $loc_title ) . '"' . $checked . $disabled . ' class="cd-child-checkbox" data-group="' . esc_attr( strtolower($state_name) ) . '"> ';
$output .= esc_html( $clean_title );
            $output .= '</label>';
        }
    }

    $output .= '</div>';

    // Include JavaScript
    $output .= <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function() {
    const masters = document.querySelectorAll('.cd-master-checkbox');
    masters.forEach(master => {
        master.addEventListener('change', function() {
            const group = this.dataset.group;
            const checkboxes = document.querySelectorAll('.cd-child-checkbox[data-group="' + group + '"]');
            checkboxes.forEach(cb => {
                if (!cb.disabled) cb.checked = master.checked;
            });
        });
    });
});
</script>
HTML;

    return $output;
}



function onesignal_create_user_and_subscribe( $phone, $location ) {
    // Normalize phone to E.164
    $digits = preg_replace('/\D+/', '', $phone);
    if ( ! str_starts_with($digits, '1') ) {
        $digits = '1' . $digits;
    }
    $formattedPhone = '+' . $digits;

    // Hardcoded OneSignal credentials
    $app_id  = '';
    $api_key = '';

    // Build URLs for upsert
    $baseUrl   = "https://api.onesignal.com/apps/{$app_id}/users/by/external_id/{$formattedPhone}";
    $createUrl = "https://api.onesignal.com/apps/{$app_id}/users";

    // Check if the user already exists using GET
    $ch = curl_init($baseUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Key {$api_key}",
        "Content-Type: application/json",
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    $getResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // If found, use PATCH; if not, use POST
    $method = ($httpCode === 200) ? 'PATCH' : 'POST';
    $url    = ($method === 'PATCH') ? $baseUrl : $createUrl;

    // Prepare the payload with:
    // - aliases (instead of identity)
    // - properties with tags (location)
    // - subscriptions for SMS
    $payload = [
        'identity' => [
            'external_id' => $formattedPhone,
        ],
        'properties' => [
            'tags' => [
                'location' => $location,
            ],
        ],
        'subscriptions' => [
            [
                'type'    => 'SMS',
                'token'   => $formattedPhone,
                'enabled' => true,
            ],
        ],
    ];

    // Send the upsert request with cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Key {$api_key}",
        "Content-Type: application/json",
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 400 || $error) {
        return new WP_Error('onesignal_error', "HTTP Error: $httpCode. Response: $response");
    }

    return json_decode($response, true);
}

/**
 * AJAX callback to create (or update) a OneSignal user & subscribe them for SMS,
 * trimming the location to only the part before the first comma.
 */
function onesignal_create_and_subscribe_callback() {
    // Grab phone & location from the AJAX request
    $phone    = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
    $full_loc = isset( $_POST['location'] ) ? sanitize_text_field( $_POST['location'] ) : '';

    if ( empty( $phone ) || empty( $full_loc ) ) {
        wp_send_json_error( array( 'message' => 'Missing phone number or location.' ) );
        wp_die();
    }

    // Trim location to only the portion before the first comma
    $parts = explode(',', $full_loc);
    $short_location = trim( $parts[0] );

    // Call our upsert function
    $result = onesignal_create_user_and_subscribe( $phone, $short_location );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    } else {
        wp_send_json_success( $result );
    }
    wp_die();
}
add_action( 'wp_ajax_onesignal_create_and_subscribe', 'onesignal_create_and_subscribe_callback' );
add_action( 'wp_ajax_nopriv_onesignal_create_and_subscribe', 'onesignal_create_and_subscribe_callback' );
