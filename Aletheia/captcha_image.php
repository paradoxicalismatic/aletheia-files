<?php
// We need to resume the session to access the CAPTCHA text
session_start();

// Set the content type header to display the image
header('Content-type: image/png');

// Define the path to your font file
$font_file = __DIR__ . '/data/fonts/arial.ttf'; 

// Get the CAPTCHA text from the session, default to 'error' if not set
$text = $_SESSION['captcha_text'] ?? 'error';

// --- Image Creation ---

// Create a blank image canvas
$image = imagecreatetruecolor(120, 40);

// Allocate colors
$bg_color = imagecolorallocate($image, 30, 30, 46); // Background from your theme
$text_color = imagecolorallocate($image, 205, 214, 244); // Text color from your theme
$noise_color = imagecolorallocate($image, 108, 112, 134); // Noise color

// Fill the background
imagefilledrectangle($image, 0, 0, 120, 40, $bg_color);

// Add some random lines for noise to make it harder for bots
for ($i = 0; $i < 5; $i++) {
    imageline($image, 0, rand() % 40, 120, rand() % 40, $noise_color);
}

// Add the text to the image
imagettftext($image, 20, rand(-5, 5), 10, 30, $text_color, $font_file, $text);

// Output the final image as a PNG
imagepng($image);

// Clean up the memory
imagedestroy($image);
?>