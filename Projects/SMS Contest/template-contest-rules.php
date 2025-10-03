<?php
/**
 * Template Name: Contest Rules
 */

get_header(); ?>


<div class="contest-page container col-sm-12 location-page-section catering-sec" >
    <h1><?php the_field('contest_name'); ?></h1>

    <p><strong>Contest Period:</strong> 
    <?php 
    $start_date = get_field('contest_start_date');
    $end_date = get_field('contest_end_datetime');

    // Check if start and end dates are available before formatting
    if ($start_date && $end_date) {
        echo 'Start Date: ' . date_i18n('F j, Y g:i a', strtotime($start_date)) . ' - End Date: ' . date_i18n('F j, Y g:i a', strtotime($end_date));
    } else {
        echo 'Contest Period dates will be announced soon.';
    }
    ?>
 <br>
    Administrator's computer is the official time-keeping device for this Contest.
</p>
    <section class="contest-rules">
        <h2>Official Rules</h2>
        <p><?php the_field('enter_rules'); ?></p>
    </section>

    <section class="contest-entry-details">
        <h2>How to Enter</h2>
        <p><?php the_field('contest_entry_details'); ?></p>
    </section>

    <section class="prize-details">
        <h2>Prize Details</h2>
        <p><?php the_field('prize_details'); ?></p>
    </section>

    <section class="additional-rules">
        <h2>Additional Rules & Restrictions</h2>
        <p><?php the_field('additional_rules_&_restrictions'); ?></p>
    </section>

    <section class="odds-details">
        <h2>Odds</h2>
        <p><?php the_field('odds_details'); ?></p>
    </section>

    <section class="privacy-details">
        <h2>Privacy Details</h2>
        <p><?php the_field('privacy_detials'); ?></p>
    </section>

    <section class="disputes-terms">
        <h2>Disputes & Terms</h2>
        <p><?php the_field('disputes_terms'); ?></p>
    </section>

    <section class="contest-sponsor">
        <h2>Sponsor</h2>
        <p><?php the_field('sponsor'); ?></p>
    </section>

    <section class="contest-administrator">
        <h2>Contest Administrator</h2>
        <p><?php the_field('contest_administrator'); ?></p>
    </section>
</div>

<?php get_footer(); ?>
