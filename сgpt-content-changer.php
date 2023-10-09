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
        "max_tokens" => 1600,
        
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
                $title_messages = array(
                    array(
                        'role' => 'user',
                        'content' => $title_prompt . ' ' . $post_title
                    ),
                );

                $content_messages = array(
                    array(
                        'role' => 'user',
                        'content' => $content_prompt . ' ' . $post_content
                    ),
                );

                // Send a chat-based completion request to OpenAI
                $altered_title = chat_completion_with_openai($title_messages, $model, $api_key);

                $altered_content = chat_completion_with_openai($content_messages, $model, $api_key);

                if ($altered_title && $altered_content) {
                    // Create a new post with the extracted title and content
                    $new_post = array(
                        'post_title' => $altered_title,
                        'post_content' => $altered_content,
                        'post_status' => 'publish',
                        'post_category' => $selected_created_categories,
                    );

                    $new_post_id = wp_insert_post($new_post);

                    if ($new_post_id) {
                        // Display a success message
                        echo '<p>Post translated and created: ' . esc_html($altered_title) . '</p>';
                    } else {
                        echo '<p>Error creating translated post for: ' . esc_html($post_title) . '</p>';
                    }
                } else {
                    echo '<p>Error translating content for: ' . esc_html($post_title) . '</p>';
                }
                
                sleep(10);
                
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
        </select><br><br>
        <input type="submit" name="translate_posts" class="button-primary" value="Translate Posts">
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

function get_ai_data() {
    $options = get_option('my_plugin_options');
    
    $url = 'https://api.openai.com/v1/chat/completions';  
    $headers = array(
        "Authorization: Bearer  {$options['api_key']}",
        "Content-Type: application/json"
    );

    $message = sanitize_text_field($_POST['message']);

    $messages = array(
                    array(
                        'role' => 'user',
                        'content' => $message
                    ),
                );
    
    $data = array(
        "model" => $options['model'],
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
        echo 'Error:' . curl_error($curl);
    } else {
        echo json_decode($result)->choices[0]->message->content;
    }
    
    curl_close($curl); 

    wp_die();
}

add_action('wp_ajax_get_ai_data', 'get_ai_data');
add_action('wp_ajax_nopriv_get_ai_data', 'get_ai_data');


function enqueue_retrieve_posts_script() {
  wp_enqueue_script('retrieve-posts', plugin_dir_url(__FILE__) . '/script.js', array('jquery'), null, true);
  wp_localize_script('retrieve-posts', 'your_ajax_object', array('ajaxurl' => admin_url('admin-ajax.php'), 'interval' => get_option('my_plugin_options')['interval'], 'title_prompt' => get_option('my_plugin_options')['title_prompt'], 'content_prompt' => get_option('my_plugin_options')['content_prompt'], 'post_to_be_status' => get_option('my_plugin_options')['post_to_be_status'], 'post_to_be_category' => get_option('my_plugin_options')['post_to_be_category'] ));
}
add_action('admin_enqueue_scripts', 'enqueue_retrieve_posts_script');

function create_post() {
    // Check if the user is logged in or has permission to create posts

    // Sanitize and retrieve the title and content from the AJAX request
    $new_title = sanitize_text_field($_POST['title']);
    $new_content = wp_kses_post($_POST['content']);

    // Retrieve current options
    $options = get_option('my_plugin_options');

    // Create a new post
    $post_data = array(
        'post_title' => $new_title,
        'post_content' => $new_content,
        'post_status' => $options['post_status'], // You can change the post status as needed
        'post_author' => get_current_user_id(), // Set the post author
        'post_category' => array($options['post_category']), // Specify the category by ID
    );

    $post_id = wp_insert_post($post_data);

    if ($post_id) {
        echo 'Post created successfully!';
    } else {
        echo 'Error creating the post.';
    }

    wp_die();
}

add_action('wp_ajax_create_post', 'create_post');
add_action('wp_ajax_nopriv_create_post', 'create_post');

// Add an admin menu item for your settings page
function my_plugin_settings_menu() {
    add_menu_page('AI Content Changer Settings', 'AI Content Changer Settings', 'manage_options', 'my-plugin-settings', 'my_plugin_settings_page');
}
add_action('admin_menu', 'my_plugin_settings_menu');

// Create the settings page
function my_plugin_settings_page() {
    if (isset($_POST['submit'])) {
        $updated_options = array(
            'api_key' => sanitize_text_field($_POST['api_key']),
            'model' => sanitize_text_field($_POST['model']),
            'title_prompt' => sanitize_text_field($_POST['title_prompt']),
            'content_prompt' => sanitize_text_field($_POST['content_prompt']),
            'interval' => intval($_POST['interval']),
            'post_to_be_status' => sanitize_text_field($_POST['post_to_be_status']),
            'post_to_be_category' => sanitize_text_field($_POST['post_to_be_category']),
            'post_status' => sanitize_text_field($_POST['post_status']),
            'post_category' => sanitize_text_field($_POST['post_category']),
        );

        update_option('my_plugin_options', $updated_options);
    }

    // Retrieve current options
    $options = get_option('my_plugin_options');

    // Display HTML form for updating options
    ?>
<div class="wrap">
    <h2>My Plugin Settings</h2>
    <div class="wrap">
        <h2>My Plugin Settings</h2>
        <form method="post">
            <label for="api_key">API Key:</label>
            <input type="text" name="api_key" value="<?php echo esc_attr($options['api_key']); ?>" /><br />

            <label for="model">Model:</label>
            <input type="text" name="model" value="<?php echo esc_attr($options['model']); ?>" /><br />

            <label for="title_prompt">Title Prompt:</label>
            <input type="text" name="title_prompt" value="<?php echo esc_attr($options['title_prompt']); ?>" /><br />

            <label for="content_prompt">Content Prompt:</label>
            <textarea name="content_prompt"><?php echo esc_textarea($options['content_prompt']); ?></textarea><br />

            <label for="interval">Interval (minutes):</label>
            <input type="number" name="interval" value="<?php echo esc_attr($options['interval']); ?>" /><br />

            <!-- Select field for post status -->
            <label for="post_to_be_status">Posts to be Transformed Status:</label>
            <select name="post_to_be_status">
                <option value="publish"
                    <?php selected('publish', isset($_POST['post_to_be_status']) ? $_POST['post_to_be_status'] : $options['post_to_be_status']); ?>>
                    Publish</option>
                <option value="draft"
                    <?php selected('draft', isset($_POST['post_to_be_status']) ? $_POST['post_to_be_status'] : $options['post_to_be_status']); ?>>
                    Draft</option>
                <option value="pending"
                    <?php selected('pending', isset($_POST['post_to_be_status']) ? $_POST['post_to_be_status'] : $options['post_to_be_status']); ?>>
                    Pending</option>
            </select><br />

            <!-- Select field for category -->
            <label for="post_to_be_category">Posts to be Transformed Category:</label>
            <select name="post_to_be_category">
                <?php
            $categories = get_categories();
            foreach ($categories as $category) {
                echo '<option value="' . esc_attr($category->term_id) . '" ' . selected($category->term_id, $options['post_to_be_category']) . '>' . esc_html($category->name) . '</option>';
            }
            ?>
            </select><br />

            <!-- Select field for post status -->
            <label for="post_status">Transformed Post Status:</label>
            <select name="post_status">
                <option value="publish"
                    <?php selected('publish', isset($_POST['post_status']) ? $_POST['post_status'] : $options['post_status']); ?>>
                    Publish</option>
                <option value="draft"
                    <?php selected('draft', isset($_POST['post_status']) ? $_POST['post_status'] : $options['post_status']); ?>>
                    Draft</option>
                <option value="pending"
                    <?php selected('pending', isset($_POST['post_status']) ? $_POST['post_status'] : $options['post_status']); ?>>
                    Pending</option>
            </select><br />

            <!-- Select field for category -->
            <label for="post_category">Transformed Post Category:</label>
            <select name="post_category">
                <?php
            $categories = get_categories();
            foreach ($categories as $category) {
                echo '<option value="' . esc_attr($category->term_id) . '" ' . selected($category->term_id, $options['post_category']) . '>' . esc_html($category->name) . '</option>';
            }
            ?>
            </select><br />

            <input type="submit" name="submit" value="Save Settings" />
        </form>
    </div>


    <button id="start-transforming">Start transforming posts</button>
    <?php
}

?>