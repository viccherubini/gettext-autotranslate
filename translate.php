#!/usr/bin/env php
<?php

$google_translate_key = '';
$directory = dirname(__FILE__) . DIRECTORY_SEPARATOR;
$locale_directory = $directory . 'locale' . DIRECTORY_SEPARATOR;

$locale_list = array(
	'en_US.UTF-8' => 'English', // Make sure English is the first one in this list
	'cs_CZ.UTF-8' => 'Český',
	'da_DK.UTF-8' => 'Danske',
	'de_DE.UTF-8' => 'Deutsch',
	'fi_FI.UTF-8' => 'Suomi',
	'fr_FR.UTF-8' => 'Français',
	'ja_JP.UTF-8' => '日本語',
	'nl_NL.UTF-8' => 'NederlanDIRECTORY_SEPARATOR',
	'el_GR.UTF-8' => 'Ελληνικά',
	'hi_IN.UTF-8' => 'हिन्दी',
	'it_IT.UTF-8' => 'Italiano',
	'ko_KR.UTF-8' => '한국의',
	'pl_PL.UTF-8' => 'Polski',
	'pt_BR.UTF-8' => 'Português (Brasil)',
	'pt_PT.UTF-8' => 'Português (Portugal)',
	'ru_RU.UTF-8' => 'Русский',
	'es_MX.UTF-8' => 'Español',
	'sv_SE.UTF-8' => 'Svenska',
	'th_TH.UTF-8' => 'ภาษาไทย'
);

$from_locale = 'en_US';

if ( 2 == $argc ) {
	$to_locale = $argv[1];
	$locale_list = array($to_locale => $to_locale);
} else {
	/* Get rid of English. */
	array_shift($locale_list);
}

$i = 0;
foreach ($locale_list as $locale => $text) {
	$to_locale = str_replace('.UTF-8', NULL, $locale);

	$from_lang = current(explode('_', $from_locale));
	$to_lang = current(explode('_', $to_locale));

	$po_dir_from = $locale_directory . $from_locale . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR;
	$po_dir_to = $locale_directory . $to_locale . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR;

	if (false === is_dir($po_dir_from)) {
		echo "{$po_dir_from} is not a valid directory. Perhaps you mistyped the locale?";
		exit(1);
	}

	if (false === is_dir($po_dir_to)) {
		mkdir($po_dir_to, 0755, true);
	}

	$po_file_from = $po_dir_from . 'messages.po';
	$po_file_to = $po_dir_to . 'messages.po';
	$mo_file_to = $po_dir_to . 'messages.mo';

	if (false === is_file($po_file_from)) {
		echo "{$po_file_from} is not a valid PO file. Perhaps you mistyped the locale?";
		exit(1);
	}

	$msgid_list = array();
	$msgid_at = false;

	$fh_from = fopen($po_file_from, 'r');
	$fh_to   = fopen($po_file_to, 'w+');

	echo "Translating from {$from_locale} to {$to_locale}.", PHP_EOL;

	/* Enter the state machine */
	while (false === feof($fh_from)) {
		$line = fgets($fh_from);

		/* Skip lines starting with a #, it's a comment. */
		if ('#' == $line[0]) {
			continue;
		}

		/* Get the msgid, that's what we're translating. */
		if (0 === strpos($line, 'msgid')) {
			$msgid_at = true;
			$msgid_list = array();

			/* Trim off the msgid. */
			$line = preg_replace('/^msgid/', NULL, $line);
		}

		/**
		 * We've found a msgstr line, so implode the last msgid list to
		 * create a single string, and then translate that.
		 */
		if (0 === strpos($line, 'msgstr')) {
			$msgid_at = false;

			$msgid_list = array_map(function($v) {
				/* Trim off the first and last characters, they are quotes. */
				$v = substr($v, 1, strlen($v)-2);
				$v = stripslashes($v);
				return $v;
			}, $msgid_list);

			$msgstr = implode('', $msgid_list);
			$msgstr_urlencoded = urlencode($msgstr);

			$url = "http://ajax.googleapis.com/ajax/services/language/translate?v=1.0&key={$google_translate_key}&q={$msgstr_urlencoded}&langpair={$from_lang}|{$to_lang}";

			$translated_json = file_get_contents($url);
			$translated_object = json_decode($translated_json);

			if (is_object($translated_object) && is_object($translated_object->responseData)) {
				$translated_text = $translated_object->responseData->translatedText;
				$translated_text = html_entity_decode($translated_text, ENT_QUOTES, 'UTF-8');

				echo $translated_text . PHP_EOL;

				/* Custom addslashes(), don't worry about single quotes. */
				$translated_text = addslashes_double($translated_text);

				fwrite($fh_to, 'msgid "' . addslashes_double($msgstr) . '"' . PHP_EOL);
				fwrite($fh_to, 'msgstr "' . $translated_text . '"' . PHP_EOL . PHP_EOL);
			}

		}

		if ($msgid_at) {
			$line = trim($line);
			if (!empty($line) ) {
				$msgid_list[] = trim($line);
			}
		}
	}

	fclose($fh_from);
	fclose($fh_to);

	/* Make the .mo file. */
	echo "Generating .MO file." . PHP_EOL . PHP_EOL;
	shell_exec('msgfmt ' . escapeshellarg($po_file_to) . ' -o ' . escapeshellarg($mo_file_to));

	$i++;
	if ($i > 5) {
		// Sleep for a sec so Google doesn't complain
		sleep(mt_rand(15, 35));
		$i = 0;
	}
}

function addslashes_double($v) {
	return str_replace('"', '\\"', $v);
}

exit(0);
