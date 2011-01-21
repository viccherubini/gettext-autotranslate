<?php

$locale = 'en_US.UTF-8';

$directory = dirname(__FILE__) . DIRECTORY_SEPARATOR;
$locale_directory = $directory . 'locale' . DIRECTORY_SEPARATOR;

setlocale(LC_ALL, $locale);
bindtextdomain('messages', $locale_directory);
textdomain('messages');

echo _('Hi, how are you doing?');
echo PHP_EOL, PHP_EOL;
