<?php
require 'db.php'; // Include your database connection file

if (isset($_GET['filename'])) {
    $filename = $_GET['filename'];
    $filepath = 'uploads/' . $filename; // Path to your uploaded images

    if (file_exists($filepath)) {
        // Set appropriate headers for image download
        header('Content-Type: image/jpeg'); // Or image/png, etc., depending on the image type
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));

        // Read the file and output it to the browser
        readfile($filepath);
        exit;
    } else {
        // Handle file not found error
        http_response_code(404);
        echo 'Image not found.';
        exit;
    }
} else {
    // Handle missing filename parameter
    http_response_code(400);
    echo 'Invalid request.';
    exit;
}
?>