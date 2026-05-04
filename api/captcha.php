<?php
start_secure_session();
header('Content-Type: image/png');

// Clean buffer
if (ob_get_length()) ob_clean();

$width = 120;
$height = 40;
$im = imagecreatetruecolor($width, $height);

// Colors
$bg = imagecolorallocate($im, 20, 20, 20); // Dark BG
$text_color = imagecolorallocate($im, 255, 255, 255); // White text
$line_color = imagecolorallocate($im, 64, 64, 64);

imagefilledrectangle($im, 0, 0, $width, $height, $bg);

// Generate Code
$code = '';
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // No I, 1, O, 0
for ($i = 0; $i < 5; $i++) {
    $code .= $chars[rand(0, strlen($chars) - 1)];
}
$_SESSION['captcha'] = $code;

// Add Noise
for ($i = 0; $i < 5; $i++) {
    imageline($im, 0, rand() % $height, $width, rand() % $height, $line_color);
}

// Draw Text
// Using built-in font if TTF not available, or basic text
// Ideally use imagettftext if font exists. For portability, use imagestring.
// Imagestring font 5 is largest built-in
$font = 5;
$x = 20;
$y = 12;

// Perturb letters slightly
for ($i = 0; $i < 5; $i++) {
    $char = $code[$i];
    imagestring($im, $font, $x + ($i * 15), $y + rand(-5, 5), $char, $text_color);
}

imagepng($im);
imagedestroy($im);
?>
