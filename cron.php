<?php
// Add admin menu item
function ai_content_changer_submenu() {
    // Add a submenu page for settings
    add_submenu_page(
        'ai-content-changer',
        'Settings',
        'Settings',
        'manage_options',
        'ai-content-changer-settings',
        'ai_content_changer_settings_page'
    );
}
add_action('admin_menu', 'ai_content_changer_submenu');

// Add a cron schedule for every 30 minutes
function add_cron_schedule($schedules) {
    $schedules['every_30_minutes'] = array(
        'interval' => 1800, // 30 minutes in seconds
        'display' => __('Every 30 Minutes', 'textdomain'),
    );
    return $schedules;
}
add_filter('cron_schedules', 'add_cron_schedule');

// Schedule the cron job when the plugin is activated
function schedule_cron_job() {
    if (!wp_next_scheduled('translate_new_posts')) {
        wp_schedule_event(time(), 'every_30_minutes', 'translate_new_posts');
    }
}
register_activation_hook(__FILE__, 'schedule_cron_job');

// Define the function to send a chat-based completion request to OpenAI
function cron_chat_completion_with_openai($messages, $model, $api_key) {
    $url = 'https://api.openai.com/v1/chat/completions';
    $headers = array(
        "Authorization: Bearer {$api_key}",
        "Content-Type: application/json",
    );

    $data = array(
        "model" => $model,
        "messages" => $messages,
        "max_tokens" => 1600,
    );

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $result = curl_exec($curl);

    if (curl_errno($curl)) {
        error_log('Error: ' . curl_error($curl));
    } else {
        return json_decode($result)->choices[0]->message->content;
    }

    curl_close($curl);
}

// Define the function to translate new posts
function translate_new_posts() {
    $api_key = get_option('ai_content_changer_api_key');
    $model = get_option('ai_content_changer_model');

    if (empty($api_key) || empty($model)) {
        error_log('API key or model is not set. Translation job skipped.');
        return; // If API key or model is not set, do nothing
    }

    $args = array(
        'post_status' => 'publish',
        'post_type' => 'post',
        'date_query' => array(
            'after' => '30 minutes ago', // Only select posts published in the last 30 minutes
        ),
    );

    $new_posts_query = new WP_Query($args);

    if ($new_posts_query->have_posts()) {
        while ($new_posts_query->have_posts()) : $new_posts_query->the_post();
            $post_id = get_the_ID();
            $post_title = get_the_title();
            $post_content = get_the_content();

            // Create chat messages using the provided prompts for title and content
            $title_prompt = get_option('ai_content_changer_title_prompt');
            $content_prompt = get_option('ai_content_changer_content_prompt');

            $title_messages = array(
                array(
                    'role' => 'user',
                    'content' => $title_prompt . ' ' . $post_title,
                ),
            );

            $content_messages = array(
                array(
                    'role' => 'user',
                    'content' => $content_prompt . ' ' . $post_content,
                ),
            );

            // Send a chat-based completion request to OpenAI
            $altered_title = cron_chat_completion_with_openai($title_messages, $model, $api_key);
            $altered_content = cron_chat_completion_with_openai($content_messages, $model, $api_key);

            if ($altered_title && $altered_content) {
                // Create a new post with the translated title and content
                $new_post = array(
                    'post_title' => $altered_title,
                    'post_content' => $altered_content,
                    'post_status' => 'draft',
                    'post_type' => 'post',
                    'post_category' => 'cron',
                );

                $new_post_id = wp_insert_post($new_post);

                if ($new_post_id) {
                    // Log a success message
                    error_log('Post translated and created: ' . $post_title);
                } else {
                    // Log an error message
                    error_log('Error creating translated post for: ' . $post_title);
                }
            } else {
                // Log an error message
                error_log('Error translating content for: ' . $post_title);
            }
        endwhile;
    }
}
add_action('translate_new_posts', 'translate_new_posts');

// Create an admin settings page
function ai_content_changer_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Check if the form has been submitted and update options
    if (isset($_POST['update_settings'])) {
        update_option('ai_content_changer_api_key', sanitize_text_field($_POST['api_key']));
        update_option('ai_content_changer_model', sanitize_text_field($_POST['model']));
        update_option('ai_content_changer_title_prompt', sanitize_text_field($_POST['title_prompt']));
        update_option('ai_content_changer_content_prompt', sanitize_text_field($_POST['content_prompt']));

        echo '<div class="updated"><p>Settings updated.</p></div>';
    }

    $api_key = get_option('ai_content_changer_api_key');
    $model = get_option('ai_content_changer_model');
    $title_prompt = get_option('ai_content_changer_title_prompt');
    $content_prompt = get_option('ai_content_changer_content_prompt');

    // Display the settings form
    ?>
<div class="wrap">
    <h2>Translate Posts Settings</h2>
    <form method="post" action="">
        <label for="api_key">API Key:</label>
        <input type="text" name="api_key" value="<?php echo esc_attr($api_key); ?>" required><br><br>

        <label for="model">ChatGPT Model:</label>
        <input type="text" name="model" value="<?php echo esc_attr($model); ?>" required><br><br>

        <label for="title_prompt">Title Prompt:</label>
        <textarea name="title_prompt" rows="2" cols="50"
            style="width: 500px;"><?php echo esc_textarea($title_prompt); ?></textarea><br><br>

        <label for="content_prompt">Content Prompt:</label>
        <textarea name="content_prompt" rows="5" cols="50"
            style="width: 500px; height: 100px;"><?php echo esc_textarea($content_prompt); ?></textarea><br><br>

        <input type="submit" name="update_settings" class="button-primary" value="Update Settings">
    </form>
</div>
<?php
}