const interval = your_ajax_object.interval;

function transformAndCreatePosts() {
    // Replace with your actual prompts
    const titlePrompt = your_ajax_object.title_prompt;
    const contentPrompt = your_ajax_object.content_prompt;

    // Access the ajaxurl variable
    const ajaxUrl = your_ajax_object.ajaxurl;

    // Replace with your WordPress site URL
    const homeUrl = window.location.origin;

    // Calculate the timestamp for half an hour ago
    const halfHourAgo = new Date();
    halfHourAgo.setMinutes(halfHourAgo.getMinutes() - interval);

    // Convert the timestamp to ISO 8601 format
    const isoTimestamp = halfHourAgo.toISOString();

    // Replace 'yourCategoryId' with the actual category ID you want to filter by
    const categoryId = your_ajax_object.post_to_be_category;

    const status = your_ajax_object.post_to_be_status;

    // Make the GET request to retrieve posts
    fetch(
        `${homeUrl}/wp-json/wp/v2/posts?after=${isoTimestamp}&categories=${categoryId}&status=${status}`,
        {
            method: "GET",
            headers: {
                "Content-Type": "application/json",
            },
        }
    )
        .then((response) => response.json())
        .then((data) => {
            if (data.length > 0) {
                console.log("Posts retrieved:", data);
            } else {
                console.log("No new posts to retrieve.");
            }

            // Iterate through retrieved posts and transform them
            data.forEach((post) => {
                const originalTitle = post.title.rendered;
                const originalContent = post.content.rendered;

                // Create a data object to send via AJAX
                const data = {
                    action: "get_ai_data",
                    message: titlePrompt + " " + originalTitle,
                };

                setTimeout(() => {
                    console.log("pause");
                }, 500);

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

                        // Send the data to the PHP script
                        jQuery.ajax({
                            url: ajaxUrl,
                            method: "POST",
                            data: data,
                            success: function (response) {
                                const newContent = response;

                                if (newTitle && newContent) {
                                    sendToPHPForPostCreation(
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
                    },
                    error: function (xhr, status, error) {
                        console.error(
                            `Error creating post (${newTitle}):`,
                            error
                        );
                    },
                });
            });
        })
        .catch((error) => {
            console.error("Error retrieving posts:", error);
        });
}

function transformWithOpenAI(inputText, prompt, apiKey) {
    const apiUrl = "https://api.openai.com/v1/chat/completions";

    return fetch(apiUrl, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${apiKey}`,
        },
        body: JSON.stringify({
            max_tokens: 1600, // Adjust the max_tokens as needed
            model: "gpt-3.5-turbo",
            messages: [
                {
                    role: "user",
                    content: inputText + " " + prompt,
                },
            ],
        }),
    })
        .then((response) => response.json())
        .then((data) => {
            return data.choices[0].message.content.trim();
        });
}

function sendToPHPForPostCreation(newTitle, newContent) {
    // Access the ajaxurl variable
    const ajaxUrl = your_ajax_object.ajaxurl;

    // Now you can use ajaxUrl in your AJAX requests

    // Create a data object to send via AJAX
    const data = {
        action: "create_post",
        title: newTitle,
        content: newContent,
    };

    // Send the data to the PHP script
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

startTransformingButton = jQuery("#start-transforming");

startTransformingButton.on("click", () => {
    console.log("Started post transforming");

    setInterval(() => {
        transformAndCreatePosts();
    }, interval * 1000);
})
    