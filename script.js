const interval = your_ajax_object.interval;
const titlePrompt = your_ajax_object.title_prompt;
const contentPrompt = your_ajax_object.content_prompt;
const ajaxUrl = your_ajax_object.ajaxurl;
const homeUrl = window.location.origin;
const categoryId = your_ajax_object.post_to_be_category;
const status = your_ajax_object.post_to_be_status;

function cgptcc_transformAndCreatePosts() {
    const data = {
        action: "cgptcc_get_recent_posts",
    };

    // Make the GET request to retrieve posts
    jQuery.ajax({
        url: ajaxUrl,
        method: "POST",
        data: data,
        success: function (data) {
            if (!data[0].title) {
                console.log("No new posts to retrieve.");
            } else {
                console.log(
                    "Posts retrieved:",
                    data[0].title,
                    data.length + " posts to transform"
                );

                // Iterate through retrieved posts and transform them
                data.forEach((post) => {
                    const originalTitle = post.title;
                    const originalContent = post.content;

                    // Create a data object to send via AJAX
                    const data = {
                        action: "get_ai_data",
                        message: titlePrompt + " " + originalTitle,
                    };

                    // Send the data to the PHP script
                    jQuery.ajax({
                        url: ajaxUrl,
                        method: "POST",
                        data: data,
                        success: function (response) {
                            // Create a data object to send via AJAX
                            const newTitle = response;
                            const data = {
                                action: "get_ai_data",
                                message: contentPrompt + " " + originalContent,
                            };

                            setTimeout(() => {
                                // Send the data to the PHP script
                                jQuery.ajax({
                                    url: ajaxUrl,
                                    method: "POST",
                                    data: data,
                                    success: function (response) {
                                        const newContent = response;

                                        if (newTitle && newContent) {
                                            cgptcc_sendToPHPForPostCreation(
                                                newTitle,
                                                newContent
                                            );
                                        } else {
                                            console.log(
                                                "Not translated, post not created!"
                                            );
                                        }
                                    },
                                    error: function (xhr, status, error) {
                                        console.error(
                                            `Error creating post (${newTitle}):`,
                                            error
                                        );
                                    },
                                });
                            }, 1000);
                        },
                        error: function (xhr, status, error) {
                            console.error(
                                `Error creating post (${newTitle}):`,
                                error
                            );
                        },
                    });
                });
            }
        },
        error: function (error) {
            console.error("Error retrieving posts:", error);
        },
    });
}

function cgptcc_sendToPHPForPostCreation(newTitle, newContent) {
    // Create a data object to send via AJAX
    const data = {
        action: "cgptcc_create_post",
        title: newTitle,
        content: newContent,
    };

    // Send the data to the PHP script asynchronously
    jQuery.ajax({
        url: ajaxUrl,
        method: "POST",
        data: data,
        success: function (response) {
            console.log(`New post created (${newTitle}):`, response);
        },
        error: function (xhr, status, error) {
            console.error(`Error creating post:`, error);
        },
    });
}

const startTransformingButton = jQuery("#start-transforming");

startTransformingButton.on("click", () => {
    console.log("Started post transforming");

    cgptcc_transformAndCreatePosts();

    setInterval(() => {
        cgptcc_transformAndCreatePosts();
    }, interval * 60 * 1000); // Convert to milliseconds
});
