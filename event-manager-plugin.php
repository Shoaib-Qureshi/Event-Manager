<?php

/**
 * Plugin Name: Event Manager
 * Description: A plugin to manage events
 * Author: Shoaib Qureshi
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Create database table on plugin activation
register_activation_hook(__FILE__, 'emp_create_events_table');
function emp_create_events_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'events';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        description text NOT NULL,
        event_date datetime NOT NULL,
        location varchar(255) NOT NULL,
        organizer varchar(255) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Add menu to WordPress admin
add_action('admin_menu', 'emp_add_admin_menu');
function emp_add_admin_menu()
{
    add_menu_page(
        'Event Manager',
        'Events',
        'manage_options',
        'event-manager',
        'emp_admin_page',
        'dashicons-calendar'
    );
}

// Admin page content
function emp_admin_page()
{
?>
    <div class="wrap">
        <h1>Event Manager</h1>
        <div id="event-manager-tabs">
            <ul class="nav-tab-wrapper">
                <li><a href="#events-list" class="nav-tab nav-tab-active">Events List</a></li>
                <li><a href="#add-event" class="nav-tab">Add New Event</a></li>
                <li><a href="#settings" class="nav-tab">Settings</a></li>
            </ul>

            <div id="events-list" class="tab-content">
                <?php echo emp_display_events([]); ?>
            </div>

            <div id="add-event" class="tab-content" style="display:none;">
                <?php echo emp_display_event_form(); ?>
            </div>

            <div id="settings" class="tab-content" style="display:none;">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('emp_settings');
                    do_settings_sections('emp_settings');
                    submit_button();
                    ?>
                </form>
            </div>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            // Tab functionality
            $('.nav-tab').click(function(e) {
                e.preventDefault();
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.tab-content').hide();
                $($(this).attr('href')).show();
            });
        });
    </script>
<?php
}

// Register settings
add_action('admin_init', 'emp_register_settings');
function emp_register_settings()
{
    register_setting('emp_settings', 'emp_default_organizer');
    register_setting('emp_settings', 'emp_organizers');

    add_settings_section(
        'emp_settings_section',
        'Default Settings',
        'emp_settings_section_callback',
        'emp_settings'
    );

    add_settings_field(
        'emp_default_organizer',
        'Default Organizer',
        'emp_default_organizer_callback',
        'emp_settings',
        'emp_settings_section'
    );

    add_settings_field(
        'emp_organizers',
        'Organizers List',
        'emp_organizers_callback',
        'emp_settings',
        'emp_settings_section'
    );
}

function emp_settings_section_callback()
{
    echo '<p>Configure default settings for your events.</p>';
}

function emp_default_organizer_callback()
{
    $value = get_option('emp_default_organizer');
    echo '<input type="text" name="emp_default_organizer" value="' . esc_attr($value) . '" />';
}

function emp_organizers_callback()
{
    $organizers = get_option('emp_organizers', []);
    $organizers = is_array($organizers) ? $organizers : [];

    echo '<div id="emp-organizers-list">';
    foreach ($organizers as $organizer) {
        echo '<div class="organizer-item">';
        echo '<input type="text" name="emp_organizers[]" value="' . esc_attr($organizer) . '" />';
        echo '<button type="button" class="button remove-organizer">Remove</button>';
        echo '</div>';
    }
    echo '</div>';
    echo '<button type="button" class="button" id="add-organizer">Add Organizer</button>';
?>
    <script>
        jQuery(document).ready(function($) {
            $('#add-organizer').on('click', function() {
                var newItem = $('<div class="organizer-item">' +
                    '<input type="text" name="emp_organizers[]" value="" />' +
                    '<button type="button" class="button remove-organizer">Remove</button>' +
                    '</div>');
                $('#emp-organizers-list').append(newItem);
            });

            $(document).on('click', '.remove-organizer', function() {
                $(this).parent().remove();
            });
        });
    </script>
<?php
}

// Display events function
function emp_display_events($atts)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'events';

    $events = $wpdb->get_results("SELECT * FROM $table_name ORDER BY event_date ASC");

    ob_start();
?>
    <div class="event-list-container">
        <h2>Upcoming Events</h2>
        <?php if (empty($events)) : ?>
            <p>No events found in the database.</p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Organizer</th>
                        <?php if (is_user_logged_in()) : ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event) : ?>
                        <tr>
                            <td><?php echo esc_html($event->title); ?></td>
                            <td><?php echo esc_html($event->description); ?></td>
                            <td><?php echo esc_html(date('F j, Y g:i A', strtotime($event->event_date))); ?></td>
                            <td><?php echo esc_html($event->location); ?></td>
                            <td><?php echo esc_html($event->organizer); ?></td>
                            <?php if (is_user_logged_in()) : ?>
                                <td>
                                    <button class="button button-small delete-event" data-id="<?php echo esc_attr($event->id); ?>">
                                        Delete
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script>
        function refreshEventList() {
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'refresh_events'
                },
                success: function(response) {
                    if (response.success) {
                        jQuery('.event-list-container').html(response.data);
                    } else {
                        alert('Error refreshing events');
                    }
                }
            });
        }
    </script>
<?php
    return ob_get_clean();
}





function emp_display_event_form()
{
    function emp_enqueue_event_styles()
    {
        if (!is_admin()) {
            wp_enqueue_style(
                'emp-event-styles',
                plugins_url('css/event-styles.css', __FILE__),
                array(),
                '1.0.1'
            );
        }
    }

    add_action('wp_enqueue_scripts', 'emp_enqueue_event_styles', 20);
    $organizers = get_option('emp_organizers', []);
    $default_organizer = get_option('emp_default_organizer');

    // Add default organizer to the list if it's not already there
    if (!empty($default_organizer) && !in_array($default_organizer, $organizers)) {
        array_unshift($organizers, $default_organizer);
    }

    ob_start();
?>
    <style>
        /* Form container style */
        .event-form-container {
            background: #ffffff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
            margin: 20px 0;
            max-width: 100% !important;
        }

        /* Form title style */
        .event-form-container h2 {
            color: #333;
            font-size: 28px;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        /* Form field styles */
        .event-form-container .form-field {
            margin-bottom: 20px;
        }

        .event-form-container .form-field label {
            display: block;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }

        .event-form-container .form-field input,
        .event-form-container .form-field textarea,
        .event-form-container .form-field select {
            width: 100%;
            padding: 10px 15px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background: #f9f9f9;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .event-form-container .form-field input:focus,
        .event-form-container .form-field textarea:focus,
        .event-form-container .form-field select:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.2);
            outline: none;
        }

        /* Button style */
        .event-form-container .button.button-primary {
            background-color: #007bff !important;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            font-size: 16px;
            font-weight: 500;
        }

        .event-form-container .button.button-primary:hover {
            background-color: #0056b3;
        }

        /* Responsive design */
        @media screen and (max-width: 768px) {
            .event-form-container {
                padding: 15px;
            }

            .event-form-container h2 {
                font-size: 24px;
            }

            .event-form-container .form-field input,
            .event-form-container .form-field textarea,
            .event-form-container .form-field select {
                font-size: 14px;
                padding: 8px 10px;
            }

            .event-form-container .button.button-primary {
                font-size: 14px;
                padding: 8px 15px;
            }
        }

        /* Custom scrollbar for form overflow */
        .event-form-container::-webkit-scrollbar {
            height: 8px;
        }

        .event-form-container::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .event-form-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .event-form-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>

    <div class="event-form-container">
        <form id="add-event-form" method="post">
            <?php wp_nonce_field('create_event', 'event_nonce'); ?>

            <div class="form-field">
                <label for="event_title">Title</label>
                <input type="text" id="event_title" name="title" required />
            </div>

            <div class="form-field">
                <label for="event_description">Description</label>
                <textarea id="event_description" name="description" required></textarea>
            </div>

            <div class="form-field">
                <label for="event_date">Date and Time</label>
                <input type="datetime-local" id="event_date" name="event_date" required />
            </div>

            <div class="form-field">
                <label for="event_location">Location</label>
                <input type="text" id="event_location" name="location" required />
            </div>

            <div class="form-field">
                <label for="event_organizer">Organizer</label>
                <select id="event_organizer" name="organizer" required>
                    <option value="">Select Organizer</option>
                    <?php foreach ($organizers as $organizer) : ?>
                        <option value="<?php echo esc_attr($organizer); ?>"
                            <?php selected($organizer, $default_organizer); ?>>
                            <?php echo esc_html($organizer); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <button type="submit" class="button button-primary">Add Event</button>
            </div>
        </form>
    </div>

    <script>
        jQuery(document).ready(function($) {
            $('#add-event-form').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                var formData = new FormData(this);
                formData.append('action', 'create_event');

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            alert('Event created successfully!');
                            form[0].reset();
                            refreshEventList();
                            if (typeof refreshEventList === 'function') {
                                refreshEventList();
                            } else {
                                location.reload();
                            }
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Server error occurred');
                    }
                });
            });

            // Add delete functionality for frontend
            $(document).on('click', '.delete-event', function() {
                if (!confirm('Are you sure you want to delete this event?')) {
                    return;
                }

                var eventId = $(this).data('id');
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'delete_event',
                        event_id: eventId,
                        nonce: '<?php echo wp_create_nonce("delete_event_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            if (typeof refreshEventList === 'function') {
                                refreshEventList();
                            } else {
                                location.reload();
                            }
                        } else {
                            alert('Error: ' + response.data);
                        }
                    }
                });
            });
        });
    </script>
<?php
    return ob_get_clean();
}

// AJAX handler for creating events
add_action('wp_ajax_create_event', 'emp_ajax_create_event');
function emp_ajax_create_event()
{
    check_ajax_referer('create_event', 'event_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    $required_fields = ['title', 'description', 'event_date', 'location', 'organizer'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            wp_send_json_error("Field '$field' is required");
            return;
        }
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'events';

    $result = $wpdb->insert(
        $table_name,
        [
            'title' => sanitize_text_field($_POST['title']),
            'description' => sanitize_textarea_field($_POST['description']),
            'event_date' => sanitize_text_field($_POST['event_date']),
            'location' => sanitize_text_field($_POST['location']),
            'organizer' => sanitize_text_field($_POST['organizer'])
        ],
        ['%s', '%s', '%s', '%s', '%s']
    );

    if ($result === false) {
        wp_send_json_error('Database error: ' . $wpdb->last_error);
        return;
    }

    wp_send_json_success(['message' => 'Event created successfully']);
}

// AJAX handler for deleting events
add_action('wp_ajax_delete_event', 'emp_ajax_delete_event');
function emp_ajax_delete_event()
{
    check_ajax_referer('delete_event_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    if (empty($_POST['event_id'])) {
        wp_send_json_error('Event ID is required');
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'events';

    $result = $wpdb->delete(
        $table_name,
        ['id' => intval($_POST['event_id'])],
        ['%d']
    );

    if ($result === false) {
        wp_send_json_error('Database error: ' . $wpdb->last_error);
        return;
    }

    wp_send_json_success(['message' => 'Event deleted successfully']);
}

// Add shortcodes
add_shortcode('event_list', 'emp_display_events');

?>

<style>
    /* Main container style */
    .event-list-container {
        background: #ffffff;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        margin: 20px 0;
        max-width: 100% !important;
    }

    /* Title style */
    .event-list-container h2 {
        color: #333;
        font-size: 28px;
        margin-bottom: 25px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
    }

    /* Table styles */
    .event-list-container table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    /* Header styles */
    .event-list-container table thead th {
        background: #f8f9fa;
        color: #333;
        font-weight: 600;
        padding: 15px;
        text-align: left;
        border-bottom: 2px solid #dee2e6;
        white-space: nowrap;
    }

    /* Cell styles */
    .event-list-container table td {
        padding: 15px;
        border-bottom: 1px solid #eee;
        color: #444;
        vertical-align: middle;
    }

    /* Row hover effect */
    .event-list-container table tbody tr:hover {
        background-color: #f8f9fa;
        transition: background-color 0.3s ease;
    }

    /* Even row background */
    .event-list-container table tbody tr:nth-child(even) {
        background-color: #fcfcfc;
    }

    /* Delete button style */
    .event-list-container .button.delete-event {
        background-color: #ff4757 !important;
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s ease;
        font-size: 14px;
        font-weight: 500;
    }

    .event-list-container .button.delete-event:hover {
        background-color: #ff6b81;

    }

    /* Empty state message */
    .event-list-container>p {
        text-align: center;
        padding: 30px;
        color: #666;
        font-style: italic;
        background: #f8f9fa;
        border-radius: 5px;
        margin: 20px 0;
    }

    /* Date column specific style */
    .event-list-container table td:nth-child(3) {
        white-space: nowrap;
    }

    /* Responsive design */
    @media screen and (max-width: 768px) {
        .event-list-container {
            padding: 15px;
            overflow-x: auto;
        }

        .event-list-container table {
            display: block;
            overflow-x: auto;
            white-space: nowrap;
        }

        .event-list-container h2 {
            font-size: 24px;
        }

        .event-list-container table th,
        .event-list-container table td {
            padding: 12px 10px;
        }
    }

    /* Custom scrollbar for table overflow */
    .event-list-container::-webkit-scrollbar {
        height: 8px;
    }

    .event-list-container::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    .event-list-container::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }

    .event-list-container::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
</style>

<?php
// Enqueue required styles
add_action('admin_enqueue_scripts', 'emp_enqueue_admin_styles');
function emp_enqueue_admin_styles($hook)
{
    if ($hook != 'toplevel_page_event-manager') {
        return;
    }

    wp_enqueue_style('emp-admin-styles', plugins_url('css/admin-styles.css', __FILE__));
}

// Add inline styles
add_action('admin_head', 'emp_add_admin_styles');
function emp_add_admin_styles()
{
?>
    <style>
        .event-form-container .form-field {
            margin-bottom: 15px;
        }

        .event-form-container label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .event-form-container input[type="text"],
        .event-form-container input[type="datetime-local"],
        .event-form-container textarea,
        .event-form-container select {
            width: 100%;
            max-width: 400px;
            padding: 8px;
        }

        .organizer-item {
            margin-bottom: 10px;
        }

        .organizer-item input {
            margin-right: 10px;
        }

        #event-manager-tabs .nav-tab-wrapper {
            margin-bottom: 20px;
        }
    </style>




<?php

    // AJAX handler for creating events
    add_action('wp_ajax_create_event', 'emp_ajax_create_event');
    add_action('wp_ajax_nopriv_create_event', 'emp_ajax_create_event'); // Allow for non-logged-in users

    // AJAX handler for deleting events
    add_action('wp_ajax_delete_event', 'emp_ajax_delete_event');
    add_action('wp_ajax_nopriv_delete_event', 'emp_ajax_delete_event'); // Allow for non-logged-in users


    add_action('wp_ajax_refresh_events', 'emp_ajax_refresh_events');
    add_action('wp_ajax_nopriv_refresh_events', 'emp_ajax_refresh_events'); // For non-logged-in users

    function emp_ajax_refresh_events()
    {
        check_ajax_referer('refresh_events_nonce', 'nonce'); // Optional for added security
        wp_send_json_success(emp_display_events([]));
    }

    add_action('wp_enqueue_scripts', 'emp_enqueue_frontend_scripts');
    function emp_enqueue_frontend_scripts()
    {
        wp_enqueue_script('emp-frontend-js', plugins_url('js/frontend.js', __FILE__), ['jquery'], null, true);

        wp_localize_script('emp-frontend-js', 'empAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('refresh_events_nonce'),
        ]);
    }
}
