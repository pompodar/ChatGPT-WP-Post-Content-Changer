const interval = your_ajax_object.interval;
const titlePrompt = your_ajax_object.title_prompt;
const contentPrompt = your_ajax_object.content_prompt;
const ajaxUrl = your_ajax_object.ajaxurl;
const homeUrl = window.location.origin;
const categoryId = your_ajax_object.post_to_be_category;
const status = your_ajax_object.post_to_be_status;

function transformAndCreatePosts() {
    setTimeout(() => {
        
    }, 1000);
    
    const data = {
        action: "get_recent_posts",
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
            console.log("Posts retrieved:", data[0].title);


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
            }
        },
        error: function (error) {
            console.error("Error retrieving posts:", error);
        },
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
    // Create a data object to send via AJAX
    const data = {
        action: "create_post",
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

    transformAndCreatePosts();

    setInterval(() => {
        transformAndCreatePosts();
    }, interval * 60 * 1000); // Convert to milliseconds
});
