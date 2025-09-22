<?php
// Get service time/date from cookies if set
$service_date = isset($_COOKIE['service_date']) ? sanitize_text_field($_COOKIE['service_date']) : '';
$service_time = isset($_COOKIE['service_time']) ? sanitize_text_field($_COOKIE['service_time']) : '';
$delivery_address = isset($_COOKIE['delivery_address']) ? sanitize_text_field($_COOKIE['delivery_address']) : '';
if(isset($_COOKIE['branch_name'])) {
    $delivery_address = $_COOKIE['branch_name'];
}
// Format date if it exists, else use today's date
if (!empty($service_date_raw)) {
    // Convert from Y-m-d (cookie) to F j (e.g., July 9)
    $date_object   = DateTime::createFromFormat('Y-m-d', $service_date_raw);
    $service_date  = $date_object ? $date_object->format('F j') : date_i18n('F j');
} else {
    $service_date = date_i18n('F j');
}

if (empty($service_time)) {
    // Get current time in WP timezone
    $now = new DateTime('now', wp_timezone());

    // Add 30 minutes
    $now->modify('+30 minutes');

    // Round up to the nearest 30 minutes
    $minutes = (int) $now->format('i');
    if ($minutes < 30) {
        $now->setTime((int)$now->format('H'), 30);
    } else {
        $now->setTime((int)$now->format('H') + 1, 0);
    }

    // Format output
    $service_time = $now->format('g:i A'); 
}
?>
<p class="rpress_order-address-wrap">
    <?php if ( is_plugin_active( 'restropress-multilocation/restropress-multilocation.php' ) ) { ?>
        <svg width="12" height="14" viewBox="0 0 12 14" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path
                d="M5.23944 0.0278769C4.12912 0.142737 3.09265 0.569361 2.19018 1.28587C1.90576 1.51012 1.38342 2.0434 1.17558 2.32234C0.122693 3.73075 -0.2465 5.50834 0.163715 7.17655C0.308657 7.76452 0.483682 8.2185 0.792711 8.81741C1.62681 10.4419 3.17196 12.162 5.06441 13.5841C5.46642 13.8849 5.677 13.9998 5.82741 13.9998C5.97235 13.9998 6.19113 13.8849 6.53845 13.6251C8.19025 12.3863 9.57677 10.9369 10.4792 9.49563C10.7336 9.09362 11.111 8.33609 11.2532 7.95322C11.5321 7.19569 11.6525 6.55029 11.6525 5.81737C11.6525 3.84834 10.6597 2.03519 8.97239 0.927614C7.9113 0.230249 6.51931 -0.103392 5.23944 0.0278769ZM6.49743 1.21476C7.52023 1.37065 8.38168 1.80821 9.1228 2.55206C9.51934 2.95134 9.76 3.28498 9.99793 3.75809C10.6105 4.99147 10.6543 6.31783 10.1183 7.6606C9.79555 8.47556 9.20484 9.41632 8.50201 10.2368C7.82652 11.0244 6.92952 11.8694 6.06807 12.534L5.82741 12.7172L5.54847 12.5011C4.35611 11.5741 3.22665 10.4282 2.50194 9.40538C1.88115 8.52752 1.42444 7.55395 1.26309 6.7636C1.04978 5.71892 1.18378 4.70706 1.6569 3.75809C2.36247 2.34969 3.71344 1.39799 5.29413 1.19289C5.51565 1.1628 6.24856 1.17648 6.49743 1.21476Z"
                fill="black" />
            <path
                d="M5.46348 3.52606C4.55007 3.69014 3.83356 4.33008 3.58196 5.19973C3.49992 5.48688 3.48077 5.98734 3.54367 6.2909C3.7187 7.16329 4.38598 7.86066 5.26657 8.09585C5.57287 8.17789 6.08153 8.17789 6.38783 8.09585C7.27116 7.86066 7.9357 7.16056 8.11346 6.27723C8.17363 5.98734 8.15175 5.48141 8.06971 5.19426C7.85093 4.43126 7.24107 3.82141 6.46987 3.59443C6.19913 3.51512 5.70687 3.4823 5.46348 3.52606ZM6.15811 4.71841C6.36321 4.78131 6.50816 4.87156 6.66951 5.03838C6.83906 5.21888 6.9129 5.36382 6.96486 5.61268C7.04417 5.98734 6.92658 6.36748 6.65037 6.64642C6.18272 7.11133 5.47168 7.11133 5.00404 6.64642C4.48443 6.12682 4.57194 5.28724 5.18727 4.86609C5.44707 4.6856 5.84361 4.62543 6.15811 4.71841Z"
                fill="black" />
        </svg>
        <span id="deliveryAddress"><?php echo esc_html($delivery_address); ?></span>
        <svg width="4" height="4" viewBox="0 0 4 4" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="2" cy="2" r="2" fill="#616161" />
        </svg>
    <?php } ?>
    <span id="deliveryDate"><?php echo esc_html($service_date); ?>,</span>
    <span id="deliveryTime"><?php echo esc_html($service_time); ?></span>
    <a id="editDateTime">Edit</a>
</p>