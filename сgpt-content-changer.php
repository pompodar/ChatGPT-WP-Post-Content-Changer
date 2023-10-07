<?php
/*
Plugin Name: ChatGPT WP Post Content Changer
Description: Plugin to change content of blog posts using OpenAI API.
Version: 1.0
Author: Your Name
*/

// Add admin menu item
function ai_content_changer_menu() {
    add_menu_page('AI Content Changer', 'AI Content Changer', 'manage_options', 'ai-content-changer', 'ai_content_changer_page');
}
add_action('admin_menu', 'ai_content_changer_menu');

// Function to send a chat-based completion request to OpenAI
function chat_completion_with_openai($messages, $model, $api_key) {
    $url = 'https://api.openai.com/v1/chat/completions';  
    $headers = array(
        "Authorization: Bearer  {$api_key}",
        "Content-Type: application/json"
    );
    
    $data = array(
        "model" => $model,
        "messages" => $messages,
        "max_tokens" => 50
    );
    
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    
    $result = curl_exec($curl);
    
    if (curl_errno($curl)) {
        echo 'Error:' . curl_error($curl);
    } else {
        return json_decode($result)->choices[0]->message->content;
    }
    
    curl_close($curl); 
}

// Create the admin page
function ai_content_changer_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Handle form submission
    if (isset($_POST['translate_posts'])) {
        $selected_source_categories = $_POST['selected_source_categories'];
        $selected_source_statuses = $_POST['selected_source_statuses'];
        $selected_created_categories = $_POST['selected_created_categories'];
        $selected_created_statuses = $_POST['selected_created_statuses'];
        $model = sanitize_text_field($_POST['model']);
        $api_key = sanitize_text_field($_POST['api_key']);
        $title_prompt = sanitize_text_field($_POST['title_prompt']);
        $content_prompt = sanitize_text_field($_POST['content_prompt']);

        $args = array(
            'category__in' => $selected_source_categories,
            'post_status' => $selected_source_statuses,
            'posts_per_page' => -1,
        );

        $posts_query = new WP_Query($args);

        if ($posts_query->have_posts()) {
            while ($posts_query->have_posts()) : $posts_query->the_post();
                $post_id = get_the_ID();
                $post_title = get_the_title();
                $post_content = get_the_content();

                // Create chat messages using the provided prompts for title and content
                $messages = array(
                    array(
                        'role' => 'user',
                        'content' => "Title: " . $title_prompt . "\n\nContent: " . $content_prompt,
                    ),
                    array(
                        'role' => 'assistant',
                        'content' => "Title: " . $post_title . "\n\nContent: " . $post_content,
                    ),
                );

                // Send a chat-based completion request to OpenAI
                $altered_content = chat_completion_with_openai($messages, $model, $api_key);

                if ($altered_content) {
                    // Extract the title and content from the altered response
                    list($new_title, $new_content) = explode("\n\nContent: ", $altered_content);

                    // Create a new post with the extracted title and content
                    $new_post = array(
                        'post_title' => $new_title,
                        'post_content' => $new_content,
                        'post_status' => 'publish',
                        'post_category' => $selected_created_categories,
                    );

                    $new_post_id = wp_insert_post($new_post);

                    if ($new_post_id) {
                        // Display a success message
                        echo '<p>Post translated and created: ' . esc_html($new_title) . '</p>';
                    } else {
                        echo '<p>Error creating translated post for: ' . esc_html($post_title) . '</p>';
                    }
                } else {
                    echo '<p>Error translating content for: ' . esc_html($post_title) . '</p>';
                }
            endwhile;
        }
    }

    // Display the form
    ?>
<div class="wrap">
    <h2>Translate Posts</h2>
    <form method="post" action="">
        <label for="selected_source_categories">Select Categories for Source Posts:</label>
        <select name="selected_source_categories[]" multiple>
            <!-- Populate with categories from WordPress -->
            <?php
            $categories = get_terms(array(
                'taxonomy' => 'category',
                'hide_empty' => false,
            ));

            foreach ($categories as $category) {
                echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
            }
            ?>
        </select><br><br>

        <label for="selected_source_statuses">Select Statuses for Source Posts:</label>
        <select name="selected_source_statuses[]" multiple>
            <!-- Populate with post statuses from WordPress -->
            <?php
            $post_statuses = get_post_statuses();
            foreach ($post_statuses as $status => $label) {
                echo '<option value="' . esc_attr($status) . '">' . esc_html($label) . '</option>';
            }
            ?>
        </select><br><br>

        <label for="model">Specify ChatGPT Model:</label>
        <input type="text" name="model" required><br><br>

        <label for="api_key">Your API Key:</label>
        <input type="text" name="api_key" required><br><br>

        <label for="title_prompt">Title Prompt:</label>
        <textarea name="title_prompt" rows="2" cols="50" style="width: 500px;"></textarea><br><br>

        <label for="content_prompt">Content Prompt:</label>
        <textarea name="content_prompt" rows="5" cols="50" style="width: 500px; height: 100px;"></textarea><br><br>

        <label for="selected_created_categories">Select Categories for Created Posts:</label>
        <select name="selected_created_categories[]" multiple>
            <!-- Populate with categories from WordPress -->
            <?php
            $categories = get_terms(array(
                'taxonomy' => 'category',
                'hide_empty' => false,
            ));

            foreach ($categories as $category) {
                echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
            }
            ?>
        </select><br><br>

        <label for="selected_created_statuses">Select Statuses for Created Posts:</label>
        <select name="selected_created_statuses[]" multiple>
            <!-- Populate with post statuses from WordPress -->
            <?php
            $post_statuses = get_post_statuses();
            foreach ($post_statuses as $status => $label) {
                echo '<option value="' . esc_attr($status) . '">' . esc_html($label) . '</option>';
            }
            ?>
        </select><br><br> <input type="submit" name="translate_posts" class="button-primary" value="Translate Posts">
    </form>
</div>
<?php
}

// Ensure that the plugin menu page is loaded
function load_plugin_menu_page() {
    add_action('admin_enqueue_scripts', 'enqueue_plugin_scripts');
}

add_action('admin_menu', 'ai_content_changer_menu');
add_action('admin_menu', 'load_plugin_menu_page');

// Enqueue styles and scripts
function enqueue_plugin_scripts() {
    wp_enqueue_style('ai-content-changer-style', plugin_dir_url(__FILE__) . 'style.css');
}

// Create custom stylesheet
function create_custom_stylesheet() {
    $css = '
        /* Add your custom CSS styles here */
    ';

    $file = fopen(plugin_dir_path(__FILE__) . 'style.css', 'w');
    fwrite($file, $css);
    fclose($file);
}

register_activation_hook(__FILE__, 'create_custom_stylesheet');

?>