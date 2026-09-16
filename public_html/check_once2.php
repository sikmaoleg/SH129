<?php
header('Content-Type: text/plain; charset=UTF-8');
echo 'php version: ' . PHP_VERSION . "\n";
echo 'gd: ' . (extension_loaded('gd') ? 'yes' : 'no') . "\n";
echo 'imagick: ' . (extension_loaded('imagick') ? 'yes' : 'no') . "\n";
echo 'exif: ' . (extension_loaded('exif') ? 'yes' : 'no') . "\n";
echo 'fileinfo: ' . (extension_loaded('fileinfo') ? 'yes' : 'no') . "\n";
if (extension_loaded('gd')) {
    print_r(gd_info());
}
if (extension_loaded('imagick')) {
    $i = new Imagick();
    echo "imagick formats: " . implode(',', array_intersect($i->queryFormats(), ['JPEG','JPG','PNG','WEBP','HEIC','HEIF'])) . "\n";
}
