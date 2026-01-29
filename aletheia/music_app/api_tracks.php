<?php
// Set the content type header to JSON, so the browser knows what to expect.
header('Content-Type: application/json');

// The path to your sounds folder.
$soundsDirectory = __DIR__ . '/sounds/';

// Scan the directory for all files and folders.
$files = scandir($soundsDirectory);

$audioFiles = [];
$allowedExtensions = ['mp3', 'wav', 'ogg', 'm4a'];

// Loop through all the found files.
foreach ($files as $file) {
    // Get information about the file path.
    $fileInfo = pathinfo($file);
    
    // Check if the file has an allowed audio extension.
    if (isset($fileInfo['extension']) && in_array(strtolower($fileInfo['extension']), $allowedExtensions)) {
        // Add the valid audio file to our list.
        $audioFiles[] = $file;
    }
}

// Convert the PHP array into a JSON string and send it to the browser.
echo json_encode($audioFiles);
?>