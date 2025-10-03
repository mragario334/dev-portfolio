<?php
/**
 * Template Name: SMS Contest
 */

get_header();
get_sidebar('banner');

// 1. Identify the current page and its parent (if any)
$current_child_id = get_the_ID();
$parent_id = wp_get_post_parent_id( $current_child_id );

// If the current page has a parent, use the parent’s title as the location name.
// (For example, if the SMS Contest page is a child of "Uptown New Orleans", that will be used.)
if ( $parent_id ) {
    $current_location = get_the_title( $parent_id );
} else {
    $current_location = get_the_title( $current_child_id );
}

// For debugging: log and output the current location
error_log("SMS Contest: current_location = {$current_location}");
echo "<script>console.log('SMS Contest: current_location = " . esc_js($current_location) . "');</script>";

// 2. Query for the active contest page (the Contest Rules page) that has this location.
// We assume that each contest page stores its locations in a pipe-delimited string in the meta key 'contest_location'
// and has 'contest_active' set to '1'. The template file is stored in the template-parts folder.
$args = array(
    'post_type'      => 'page',
    'meta_key'       => '_wp_page_template',
    'meta_value'     => 'template-parts/template-contest-rules.php', // ensure this matches your actual file path
    'meta_query'     => array(
        array(
            'key'     => 'contest_active',
            'value'   => '1',
            'compare' => '='
        ),
        array(
            'key'     => 'contest_location',
            'value'   => '|' . $current_location . '|', // looking for pipe-delimited value
            'compare' => 'LIKE'
        )
    )
);

$contest_query     = new WP_Query($args);
$contest_keyword   = '';
$contest_permalink = '';

// Log the query args for debugging
error_log("SMS Contest: WP_Query args = " . print_r($args, true));

if ( $contest_query->have_posts() ) {
    // We expect only one matching contest per location.
    $contest_query->the_post();
    $contest_keyword   = get_post_field('post_name', get_the_ID());
    $contest_permalink = get_permalink(get_the_ID());
    
    error_log("SMS Contest: Found contest page ID = " . get_the_ID() . " slug = " . $contest_keyword);
    echo "<script>console.log('SMS Contest: Found contest page ID " . esc_js(get_the_ID()) . " with slug " . esc_js($contest_keyword) . "');</script>";
}
wp_reset_postdata();

error_log("SMS Contest: found_posts = " . $contest_query->found_posts);
echo "<script>console.log(" . json_encode([
    'SMS Contest Query Args' => $args,
    'found_posts' => $contest_query->found_posts
]) . ");</script>";
?>

<?php if ( get_field('display_cm') != '0' ) : ?>
<section class="sms-contest-section">
    <div class="container" style="padding-top:20px;">
        <?php if ( !empty($contest_keyword) ) : ?>
            <!-- Active contest found for the current location: display the contest form -->
            <div class="row">
                <div class="col-sm-12">
                    <div class="header-center text-center">
                        <div class="section-title">JOIN OUR SMS CONTEST!</div>
                        <span id="contest-message-top">Enter your details below for a chance to win exclusive prizes!</span>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-4 offset-sm-4">
                    <form id="sms-contest-form" action="" method="post">
                        <div class="form-group">
                            <label for="first-name" class="contest-label">First Name:</label>
                            <input type="text" id="first-name" name="first_name" class="form-control" placeholder="Enter your first name" required>
                        </div>
                        <div class="form-group">
                            <label for="last-name" class="contest-label">Last Name:</label>
                            <input type="text" id="last-name" name="last_name" class="form-control" placeholder="Enter your last name" required>
                        </div>
                        <div class="form-group">
                            <label for="phone-number" class="contest-label">Phone Number:</label>
                            <input type="tel" id="phone-number" name="phone_number" class="form-control" placeholder="Enter your phone number" required>
                        </div>
                        <!-- Pass the contest keyword as a hidden field -->
                        <input type="hidden" name="contest_keyword" value="<?php echo esc_attr($contest_keyword); ?>">
                        <button type="submit" class="btn btn-primary contest-submit">Submit</button>
                        <p style="font-size: 12px; color: #555; text-align: center; margin-top: 10px;">
                            By entering this contest, you agree to receive automated, personalized, informational and marketing text messages (if consented) at this number from Felipe's Taqueria.
                            Consent is not a condition of purchase. Message and data rates may apply, frequency varies. Reply STOP to opt out.
                        </p>
                    </form>
                    <p style="text-align: center; margin-top: 15px;">
                        <?php if ( !empty($contest_permalink) ) : ?>
                            <!-- Link to the active contest page, which serves as the Terms & Conditions page -->
                            <a href="<?php echo esc_url($contest_permalink); ?>">Terms and Conditions</a>
                        <?php else : ?>
                            <span style="color: #999;">No terms and conditions link found.</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php else : ?>
            <!-- No active contest for the current location: display message -->
            <div class="row">
                <div class="col-sm-12 text-center">
                    <div class="section-title">Contest not active for this location</div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php 
get_footer();

// Optionally, you can output additional debug info to the console
// console_log_contest_pages();  // Uncomment if you have that function defined
?>

<!-- Include jQuery if not already loaded -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Pass the contest keyword to JS for form submission
    var contestKeyword = "<?php echo esc_js($contest_keyword); ?>";
</script>
<script>
jQuery(document).ready(function($) {
    $('#sms-contest-form').on('submit', function(e) {
        e.preventDefault();
        
        // Prevent submission if no contest is active
        if (!contestKeyword) {
            alert('No contests active, come back next time!');
            return;
        }

        var phoneNumber = $('#phone-number').val();
        var firstName   = $('#first-name').val();
        var lastName    = $('#last-name').val();

        // Validate phone number format (expecting 10 digits)
        var phoneRegex = /^\d{10}$/;
        if (!phoneRegex.test(phoneNumber)) {
            alert('Please enter a valid 10-digit phone number.');
            return;
        }

        $.ajax({
            type: "POST",
            url: "/wp-content/themes/felipestaqueria/inc/sms_contest.php",
            data: { 
                phone_number:   phoneNumber,
                first_name:     firstName,
                last_name:      lastName,
                externalId:     phoneNumber,
                contest_keyword: contestKeyword
            },
            success: function(response) {
                // Hide the form and display a thank you message.
                $('#sms-contest-form').hide();
                $('#contest-message-top').hide();
                $('.section-title').text('Thanks for submitting!').show();
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                alert('There was an error. Please try again.');
            }
        });
    });
});
</script>
