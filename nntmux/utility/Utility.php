<?php
namespace nntmux\utility;

use App\Models\Settings;
use App\Extensions\util\Versions;
use nntmux\db\DB;
use nntmux\ColorCLI;
use nntmux\Logger;


/**
 * Class Utility
 *
 * @package nntmux\utility
 */
class Utility
{
	/**
	 *  Regex for detecting multi-platform path. Use it where needed so it can be updated in one location as required characters get added.
	 */
	const PATH_REGEX = '(?P<drive>[A-Za-z]:|)(?P<path>[/\w.-]+|)';

	const VERSION_REGEX = '#(?P<all>v(?P<digits>(?P<major>\d+)\.(?P<minor>\d+)\.(?P<revision>\d+)(?:\.(?P<fix>\d+))?)(?:-(?P<suffix>(?:RC\d+|dev)))?)#';

	/**
	 * Checks all levels of the supplied path are readable and executable by current user.
	 *
	 * @todo Make this recursive with a switch to only check end point.
	 * @param $path	*nix path to directory or file
	 *
	 * @return bool|string True is successful, otherwise the part of the path that failed testing.
	 */
	public static function canExecuteRead($path)
	{
		$paths = explode('#/#', $path);
		$fullPath = DS;
		foreach ($paths as $singlePath) {
			if ($singlePath !== '') {
				$fullPath .= $singlePath . DS;
				if (!is_readable($fullPath) || !is_executable($fullPath)) {
					return "The '$fullPath' directory must be readable and executable by all ." .PHP_EOL;
				}
			}
		}
		return true;
	}

	/**
	 *
	 */
	public static function clearScreen(): void
	{
		if (self::isCLI()) {
			if (self::isWin()) {
				passthru('cls');
			} else {
				passthru('clear');
			}
		}
	}

	/**
	 * Replace all white space chars for a single space.
	 *
	 * @param string $text
	 *
	 * @return string
	 *
	 * @static
	 * @access public
	 */
	public static function collapseWhiteSpace($text): string
	{
		// Strip leading/trailing white space.
		return trim(
		// Replace 2 or more white space for a single space.
			preg_replace('/\s{2,}/',
				' ',
				// Replace new lines and carriage returns. DO NOT try removing '\r' or '\n' as they are valid in queries which uses this method.
				str_replace(["\n", "\r"], ' ', $text)
			)
		);
	}

	/**
	 * Removes the preceeding or proceeding portion of a string
	 * relative to the last occurrence of the specified character.
	 * The character selected may be retained or discarded.
	 *
	 * @param string $character      the character to search for.
	 * @param string $string         the string to search through.
	 * @param string $side           determines whether text to the left or the right of the character is returned.
	 *                               Options are: left, or right.
	 * @param bool   $keep_character determines whether or not to keep the character.
	 *                               Options are: true, or false.
	 *
	 * @return string
	 */
	public static function cutStringUsingLast($character, $string, $side, $keep_character = true): string
	{
		$offset = ($keep_character ? 1 : 0);
		$whole_length = strlen($string);
		$right_length = (strlen(strrchr($string, $character)) - 1);
		$left_length = ($whole_length - $right_length - 1);
		switch ($side) {
			case 'left':
				$piece = substr($string, 0, $left_length + $offset);
				break;
			case 'right':
				$start = (0 - ($right_length + $offset));
				$piece = substr($string, $start);
				break;
			default:
				$piece = false;
				break;
		}

		return $piece;
	}

	/**
	 * @param array|null $options
	 *
	 * @return array|null
	 */
	public static function getDirFiles(array $options = null): ?array
	{
		$defaults = [
			'dir'   => false,
			'ext'   => '', // no full stop (period) separator should be used.
			'file'	=> true,
			'path'  => '',
			'regex' => '',
		];
		$options += $defaults;
		if (!$options['dir'] && !$options['file']) {
			return null;
		}

		// Replace windows style path separators with unix style.
		$iterator = new \FilesystemIterator(
			str_replace('\\', '/', $options['path']),
			\FilesystemIterator::KEY_AS_PATHNAME |
			\FilesystemIterator::SKIP_DOTS |
			\FilesystemIterator::UNIX_PATHS
		);

		$files = [];
		foreach ($iterator as $fileInfo) {
			$file = $iterator->key();
			switch (true) {
				case !$options['dir'] && $fileInfo->isDir():
					break;
				case !empty($options['ext']) && $fileInfo->getExtension() != $options['ext'];
					break;
				case (empty($options['regex']) || !preg_match($options['regex'], $file)):
					break;
				case (!$options['file'] && $fileInfo->isFile()):
					break;
				default:
					$files[] = $file;
			}
		}

		return $files;
	}

	/**
	 * @return array
	 */
	public static function getThemesList(): array
	{
		$themes = scandir(NN_THEMES, SCANDIR_SORT_ASCENDING);
		$themelist[] = 'None';
		foreach ($themes as $theme) {
			if (strpos($theme, '.') === false &&
				is_dir(NN_THEMES . $theme) &&
				ucfirst($theme) === $theme
			) {
				$themelist[] = $theme;
			}
		}

		sort($themelist);
		return $themelist;
	}

	public static function getValidVersionsFile()
	{
		return (new Versions())->getValidVersionsFile();
	}

	/**
	 * Detect if the command is accessible on the system.
	 *
	 * @param $cmd
	 *
	 * @return bool|null Returns true if found, false if not found, and null if which is not detected.
	 */
	public static function hasCommand($cmd): ?bool
	{
		if ('HAS_WHICH') {
			$returnVal = shell_exec("which $cmd");

			return (empty($returnVal) ? false : true);
		}

		return null;
	}

	/**
	 * Check for availability of which command
	 */
	public static function hasWhich(): bool
	{
		exec('which which', $output, $error);

		return !$error;
	}

	/**
	 * Check if user is running from CLI.
	 *
	 * @return bool
	 */
	public static function isCLI()
	{
		return (strtolower(PHP_SAPI) === 'cli');
	}

	public static function isGZipped($filename)
	{
		$gzipped = null;
		if (($fp = fopen($filename, 'rb')) !== false) {
			if (@fread($fp, 2) == "\x1F\x8B") { // this is a gzip'd file
				fseek($fp, -4, SEEK_END);
				if (strlen($datum = @fread($fp, 4)) == 4) {
					$gzipped = $datum;
				}
			}
			fclose($fp);
		}

		return $gzipped;
	}

	/**
	 * @param DB|null $pdo
	 *
	 * @return bool
	 * @throws \Exception
	 * @throws \RuntimeException
	 */
	public static function isPatched(DB $pdo = null): bool
	{
		$versions = self::getValidVersionsFile();

		if (!($pdo instanceof DB)) {
			$pdo = new DB();
		}
		$patch = Settings::value('..sqlpatch');
		$ver = $versions->versions->sql->file;

		// Check database patch version
		if ($patch < $ver) {
			$message = "\nYour database is not up to date. Reported patch levels\n   Db: $patch\nfile: $ver\nPlease update.\n php " .
				NN_ROOT . "./tmux nntmux:db\n";
			if (self::isCLI()) {
				echo ColorCLI::error($message);
			}
			throw new \RuntimeException($message);
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public static function isWin(): bool
	{
		return stripos(PHP_OS,'win') === 0;
	}

	/**
	 * @param array  $elements
	 * @param string $prefix
	 *
	 * @return string
	 */
	public static function pathCombine(array $elements, $prefix = ''): string
	{
		return $prefix . implode(DS, $elements);
	}

	/**
	 * @param $text
	 */
	public static function stripBOM(&$text): void
	{
		$bom = pack('CCC', 0xef, 0xbb, 0xbf);
		if (0 === strncmp($text, $bom, 3)) {
			$text = substr($text, 3);
		}
	}

	/**
	 * Strips non-printing characters from a string.
	 *
	 * Operates directly on the text string, but also returns the result for situations requiring a
	 * return value (use in ternary, etc.)/
	 *
	 * @param $text        String variable to strip.
	 *
	 * @return string    The stripped variable.
	 */
	public static function stripNonPrintingChars(&$text): string
	{
		$lowChars = [
			"\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07",
			"\x08", "\x09", "\x0A", "\x0B", "\x0C", "\x0D", "\x0E", "\x0F",
			"\x10", "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17",
			"\x18", "\x19", "\x1A", "\x1B", "\x1C", "\x1D", "\x1E", "\x1F",
		];
		$text = str_replace($lowChars, '', $text);

		return $text;
	}

	/**
	 * @param $path
	 *
	 * @return string
	 */
	public static function trailingSlash($path): string
	{
		if (substr($path, strlen($path) - 1) !== '/') {
			$path .= '/';
		}

		return $path;
	}

	/**
	 * Unzip a gzip file, return the output. Return false on error / empty.
	 *
	 * @param string $filePath
	 *
	 * @return bool|string
	 */
	public static function unzipGzipFile($filePath)
	{
		/* Potential issues with this, so commenting out.
		$length = Utility::isGZipped($filePath);
		if ($length === false || $length === null) {
			return false;
		}*/

		$string = '';
		$gzFile = @gzopen($filePath, 'rb', 0);
		if ($gzFile) {
			while (!gzeof($gzFile)) {
				$temp = gzread($gzFile, 1024);
				// Check for empty string.
				// Without this the loop would be endless and consume 100% CPU.
				// Do not set $string empty here, as the data might still be good.
				if (!$temp) {
					break;
				}
				$string .= $temp;
			}
			gzclose($gzFile);
		}

		return ($string === '' ? false : $string);
	}

	public static function setCoversConstant($path)
	{
		if (!defined('NN_COVERS')) {
			switch (true) {
				case (substr($path, 0, 1) == '/' ||
					substr($path, 1, 1) == ':' ||
					substr($path, 0, 1) == '\\'):
					define('NN_COVERS', self::trailingSlash($path));
					break;
				case (strlen($path) > 0 && substr($path, 0, 1) != '/' && substr($path, 1, 1) != ':' &&
					substr($path, 0, 1) != '\\'):
					define('NN_COVERS', realpath(NN_ROOT . self::trailingSlash($path)));
					break;
				case empty($path): // Default to resources location.
				default:
					define('NN_COVERS', NN_RES . 'covers' . DS);
			}
		}
	}

	/**
	 * Creates an array to be used with stream_context_create() to verify openssl certificates
	 * when connecting to a tls or ssl connection when using stream functions (fopen/file_get_contents/etc).
	 *
	 * @param bool $forceIgnore Force ignoring of verification.
	 *
	 * @return array
	 * @static
	 * @access public
	 */
	public static function streamSslContextOptions($forceIgnore = false): array
	{
		if (empty(NN_SSL_CAFILE) && empty(NN_SSL_CAPATH)) {
			$options = [
				'verify_peer'       => false,
				'verify_peer_name'  => false,
				'allow_self_signed' => true,
			];
		} else {
			$options = [
				'verify_peer'       => $forceIgnore ? false : (bool)NN_SSL_VERIFY_PEER,
				'verify_peer_name'  => $forceIgnore ? false : (bool)NN_SSL_VERIFY_HOST,
				'allow_self_signed' => $forceIgnore ? true : (bool)NN_SSL_ALLOW_SELF_SIGNED,
			];
			if (!empty(NN_SSL_CAFILE)) {
				$options['cafile'] = NN_SSL_CAFILE;
			}
			if (!empty(NN_SSL_CAPATH)) {
				$options['capath'] = NN_SSL_CAPATH;
			}
		}
		// If we set the transport to tls and the server falls back to ssl,
		// the context options would be for tls and would not apply to ssl,
		// so set both tls and ssl context in case the server does not support tls.
		return ['tls' => $options, 'ssl' => $options];
	}

	/**
	 * Set curl context options for verifying SSL certificates.
	 *
	 * @param bool $verify false = Ignore config.php and do not verify the openssl cert.
	 *                     true  = Check config.php and verify based on those settings.
	 *                     If you know the certificate will be self-signed, pass false.
	 *
	 * @return array
	 * @static
	 * @access public
	 */
	public static function curlSslContextOptions($verify = true): array
	{
		$options = [];
		if ($verify && NN_SSL_VERIFY_HOST && (!empty(NN_SSL_CAFILE) || !empty(NN_SSL_CAPATH))) {
			$options += [
				CURLOPT_SSL_VERIFYPEER => (bool)NN_SSL_VERIFY_PEER,
				CURLOPT_SSL_VERIFYHOST => NN_SSL_VERIFY_HOST ? 2 : 0,
			];
			if (!empty(NN_SSL_CAFILE)) {
				$options += [CURLOPT_CAINFO => NN_SSL_CAFILE];
			}
			if (!empty(NN_SSL_CAPATH)) {
				$options += [CURLOPT_CAPATH => NN_SSL_CAPATH];
			}
		} else {
			$options += [
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => 0,
			];
		}

		return $options;
	}

	/**
	 * Use cURL To download a web page into a string.
	 *
	 * @param array $options See details below.
	 *
	 * @return bool|mixed
	 * @access public
	 * @static
	 */
	public static function getUrl(array $options = [])
	{
		$defaults = [
			'url'            => '',    // String ; The URL to download.
			'method'         => 'get', // String ; Http method, get/post/etc..
			'postdata'       => '',    // String ; Data to send on post method.
			'language'       => '',    // String ; Language in request header string.
			'debug'          => false, // Bool   ; Show curl debug information.
			'useragent'      => '',    // String ; User agent string.
			'cookie'         => '',    // String ; Cookie string.
			'requestheaders' => [],    // Array  ; List of request headers.
			//          Example: ["Content-Type: application/json", "DNT: 1"]
			'verifycert'     => true,  // Bool   ; Verify certificate authenticity?
			//          Since curl does not have a verify self signed certs option,
			//          you should use this instead if your cert is self signed.
		];

		$options += $defaults;

		if (!$options['url']) {
			return false;
		}

		switch ($options['language']) {
			case 'fr':
			case 'fr-fr':
				$options['language'] = 'fr-fr';
				break;
			case 'de':
			case 'de-de':
				$options['language'] = 'de-de';
				break;
			case 'en-us':
				$options['language'] = 'en-us';
				break;
			case 'en-gb':
				$options['language'] = 'en-gb';
				break;
			case '':
			case 'en':
			default:
				$options['language'] = 'en';
		}
		$header[] = 'Accept-Language: ' . $options['language'];
		if (is_array($options['requestheaders'])) {
			$header += $options['requestheaders'];
		}

		$ch = curl_init();

		$context = [
			CURLOPT_URL            => $options['url'],
			CURLOPT_HTTPHEADER     => $header,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_FOLLOWLOCATION => 1,
			CURLOPT_TIMEOUT        => 15
		];
		$context += self::curlSslContextOptions($options['verifycert']);
		if (!empty($options['useragent'])) {
			$context += [CURLOPT_USERAGENT => $options['useragent']];
		}
		if (!empty($options['cookie'])) {
			$context += [CURLOPT_COOKIE => $options['cookie']];
		}
		if ($options['method'] === 'post') {
			$context += [
				CURLOPT_POST       => 1,
				CURLOPT_POSTFIELDS => $options['postdata']
			];
		}
		if ($options['debug']) {
			$context += [
				CURLOPT_HEADER      => true,
				CURLINFO_HEADER_OUT => true,
				CURLOPT_NOPROGRESS  => false,
				CURLOPT_VERBOSE     => true
			];
		}
		curl_setopt_array($ch, $context);

		$buffer = curl_exec($ch);
		$err = curl_errno($ch);
		curl_close($ch);

		if ($err !== 0) {
			return false;
		}

		return $buffer;
	}


	/**
	 * Get human readable size string from bytes.
	 *
	 * @param int $bytes     Bytes number to convert.
	 * @param int $precision How many floating point units to add.
	 *
	 * @return string
	 */
	public static function bytesToSizeString($bytes, $precision = 0): string
	{
		if ($bytes === 0) {
			return '0B';
		}
		$unit = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];

		return round($bytes / (1024 ** $i = floor(log($bytes, 1024))), $precision) . $unit[(int)$i];
	}

	/**
	 * @param array $options
	 *
	 * @return string
	 */
	public static function getCoverURL(array $options = []): string
	{
		$defaults = [
			'id'     => null,
			'suffix' => '-cover.jpg',
			'type'   => '',
		];
		$options += $defaults;
		$fileSpecTemplate = '%s/%s%s';
		$fileSpec = '';

		if (!empty($options['id']) && in_array($options['type'],
				['anime', 'audio', 'audiosample', 'book', 'console', 'games', 'movies', 'music', 'preview', 'sample', 'tvrage', 'video', 'xxx'], false
			)
		) {
			$fileSpec = sprintf($fileSpecTemplate, $options['type'], $options['id'], $options['suffix']);
			$fileSpec = file_exists(NN_COVERS . $fileSpec) ? $fileSpec :
				sprintf($fileSpecTemplate, $options['type'], 'no', $options['suffix']);
		}

		return $fileSpec;
	}

	/**
	 * Converts XML to an associative array with namespace preservation -- use if intending to JSON encode
	 * @author Tamlyn from Outlandish.com
	 *
	 * @param \SimpleXMLElement $xml The SimpleXML parsed XML string data
	 * @param array             $options
	 *
	 * @return array            The associate array of the XML namespaced file
	 */
	public static function xmlToArray(\SimpleXMLElement $xml, array $options = []): array
	{
		$defaults = array(
			'namespaceSeparator' => ':',//you may want this to be something other than a colon
			'attributePrefix' => '@',   //to distinguish between attributes and nodes with the same name
			'alwaysArray' => [],   //array of xml tag names which should always become arrays
			'autoArray' => true,        //only create arrays for tags which appear more than once
			'textContent' => '$',       //key used for the text content of elements
			'autoText' => true,         //skip textContent key if node has no attributes or child nodes
			'keySearch' => false,       //optional search and replace on tag and attribute names
			'keyReplace' => false       //replace values for above search values (as passed to str_replace())
		);
		$options = array_merge($defaults, $options);
		$namespaces = $xml->getDocNamespaces();
		$namespaces[''] = null; //add base (empty) namespace

		$attributesArray = $tagsArray = [];
		foreach ($namespaces as $prefix => $namespace) {
			//get attributes from all namespaces
			foreach ($xml->attributes($namespace) as $attributeName => $attribute) {
				//replace characters in attribute name
				if ($options['keySearch']) $attributeName =
					str_replace($options['keySearch'], $options['keyReplace'], $attributeName);
				$attributeKey = $options['attributePrefix']
					. ($prefix ? $prefix . $options['namespaceSeparator'] : '')
					. $attributeName;
				$attributesArray[$attributeKey] = (string)$attribute;
			}
			//get child nodes from all namespaces
			foreach ($xml->children($namespace) as $childXml) {
				//recurse into child nodes
				$childArray = self::xmlToArray($childXml, $options);
				list($childTagName, $childProperties) = each($childArray);

				//replace characters in tag name
				if ($options['keySearch']) $childTagName =
					str_replace($options['keySearch'], $options['keyReplace'], $childTagName);
				//add namespace prefix, if any
				if ($prefix) $childTagName = $prefix . $options['namespaceSeparator'] . $childTagName;

				if (!isset($tagsArray[$childTagName])) {
					//only entry with this key
					//test if tags of this type should always be arrays, no matter the element count
					$tagsArray[$childTagName] =
						in_array($childTagName, $options['alwaysArray'], false) || !$options['autoArray']
							? array($childProperties) : $childProperties;
				} elseif (
					is_array($tagsArray[$childTagName]) && array_keys($tagsArray[$childTagName])
					=== range(0, count($tagsArray[$childTagName]) - 1)
				) {
					//key already exists and is integer indexed array
					$tagsArray[$childTagName][] = $childProperties;
				} else {
					//key exists so convert to integer indexed array with previous value in position 0
					$tagsArray[$childTagName] = array($tagsArray[$childTagName], $childProperties);
				}
			}
		}

		//get text content of node
		$textContentArray = [];
		$plainText = trim((string)$xml);
		if ($plainText !== '') $textContentArray[$options['textContent']] = $plainText;

		//stick it all together
		$propertiesArray = !$options['autoText'] || $attributesArray || $tagsArray || ($plainText === '')
			? array_merge($attributesArray, $tagsArray, $textContentArray) : $plainText;

		//return node as array
		return array(
			$xml->getName() => $propertiesArray
		);
	}


	// Central function for sending site email.

	/**
	 * @param $to
	 * @param $subject
	 * @param $contents
	 * @param $from
	 *
	 * @return bool
	 * @throws \nntmux\LoggerException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 * @throws \phpmailerException
	 */
	public static function sendEmail($to, $subject, $contents, $from): bool
	{
		$mail = new \PHPMailer;

		//Setup the body first since we need it regardless of sending method.
		$eol = PHP_EOL;

		$body = '<html>' . $eol;
		$body .= '<body style=\'font-family:Verdana, Verdana, Geneva, sans-serif; font-size:12px; color:#666666;\'>' . $eol;
		$body .= $contents;
		$body .= '</body>' . $eol;
		$body .= '</html>' . $eol;

		// If the mailer couldn't instantiate there's a good chance the user has an incomplete update & we should fallback to php mail()
		// @todo Log this failure.
		if (!defined('PHPMAILER_ENABLED') || PHPMAILER_ENABLED !== true || !($mail instanceof \PHPMailer)) {
			$headers = 'From: ' . $from . $eol;
			$headers .= 'Reply-To: ' . $from . $eol;
			$headers .= 'Return-Path: ' . $from . $eol;
			$headers .= 'X-Mailer: newznab' . $eol;
			$headers .= 'MIME-Version: 1.0' . $eol;
			$headers .= 'Content-type: text/html; charset=iso-8859-1' . $eol;
			$headers .= $eol;

			(new Logger())->log(__CLASS__, __FUNCTION__, 'Phpmailer could not be instantiated, falling back to PHP mail() function', Logger::LOG_ERROR);

			return mail($to, $subject, $body, $headers);
		}

		// Check to make sure the user has their settings correct.
		if (PHPMAILER_USE_SMTP === true) {
			if ((!defined('PHPMAILER_SMTP_HOST') || PHPMAILER_SMTP_HOST === '') ||
				(!defined('PHPMAILER_SMTP_PORT') || PHPMAILER_SMTP_PORT === '')
			) {
				throw new \phpmailerException(
					'You opted to use SMTP but the PHPMAILER_SMTP_HOST and/or PHPMAILER_SMTP_PORT is/are not defined correctly! Either fix the missing/incorrect values or change PHPMAILER_USE_SMTP to false in the www/settings.php'
				);
			}

			// If the user enabled SMTP & Auth but did not setup credentials, throw an exception.
			if (defined('PHPMAILER_SMTP_AUTH') && PHPMAILER_SMTP_AUTH === true) {
				if ((!defined('PHPMAILER_SMTP_USER') || PHPMAILER_SMTP_USER === '') ||
					(!defined('PHPMAILER_SMTP_PASSWORD') || PHPMAILER_SMTP_PASSWORD === '')
				) {
					throw new \phpmailerException(
						'You opted to use SMTP and SMTP Auth but the PHPMAILER_SMTP_USER and/or PHPMAILER_SMTP_PASSWORD is/are not defined correctly. Please set them in www/settings.php'
					);
				}
			}
		}

		//Finally we can send the mail.
		$mail->isHTML(true);

		if (PHPMAILER_USE_SMTP) {
			$mail->isSMTP();

			$mail->Host = PHPMAILER_SMTP_HOST;
			$mail->Port = PHPMAILER_SMTP_PORT;

			$mail->SMTPSecure = PHPMAILER_SMTP_SECURE;

			if (PHPMAILER_SMTP_AUTH) {
				$mail->SMTPAuth = true;
				$mail->Username = PHPMAILER_SMTP_USER;
				$mail->Password = PHPMAILER_SMTP_PASSWORD;
			}
		}

		$fromEmail = (PHPMAILER_FROM_EMAIL === '') ? Settings::value('site.main.email') : PHPMAILER_FROM_EMAIL;
		$fromName  = (PHPMAILER_FROM_NAME === '') ? Settings::value('site.main.title') : PHPMAILER_FROM_NAME;
		$replyTo   = (PHPMAILER_REPLYTO === '') ? $from : PHPMAILER_REPLYTO;

		(PHPMAILER_BCC !== '') ? $mail->addBCC(PHPMAILER_BCC) : null;

		$mail->setFrom($fromEmail, $fromName);
		$mail->addAddress($to);
		$mail->addReplyTo($replyTo);
		$mail->Subject = $subject;
		$mail->Body = $body;
		$mail->AltBody = $mail->html2text($body, true);

		$sent = $mail->send();

		if (!$sent) {
			(new Logger())->log(__CLASS__, __FUNCTION__, $mail->ErrorInfo, Logger::LOG_ERROR);
			throw new \phpmailerException('Unable to send mail. Error: ' . $mail->ErrorInfo);
		}

		return $sent;
	}

	/**
	 * Return file type/info using magic numbers.
	 * Try using `file` program where available, fallback to using PHP's finfo class.
	 *
	 * @param string $path Path to the file / folder to check.
	 *
	 * @return string File info. Empty string on failure.
	 * @throws \Exception
	 */
	public static function fileInfo($path)
	{
		$magicPath = Settings::value('apps.indexer.magic_file_path');
		if (self::hasCommand('file') && (!self::isWin() || !empty($magicPath))) {
			$magicSwitch = empty($magicPath) ? '' : " -m $magicPath";
			$output = self::runCmd('file' . $magicSwitch . ' -b "' . $path . '"');

			if (is_array($output)) {
				switch (count($output)) {
					case 0:
						$output = '';
						break;
					case 1:
						$output = $output[0];
						break;
					default:
						$output = implode(' ', $output);
						break;
				}
			} else {
				$output = '';
			}
		} else {
			$fileInfo = empty($magicPath) ? finfo_open(FILEINFO_RAW) : finfo_open(FILEINFO_RAW, $magicPath);

			$output = finfo_file($fileInfo, $path);
			if (empty($output)) {
				$output = '';
			}
			finfo_close($fileInfo);
		}

		return $output;
	}


	/**
	 * @param $code
	 *
	 * @return bool
	 */
	public function checkStatus($code)
	{
		return ($code === 0) ? true : false;
	}

	/**
	 * Convert Code page 437 chars to UTF.
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public static function cp437toUTF($string): string
	{
		return iconv('CP437', 'UTF-8//IGNORE//TRANSLIT', $string);
	}

	/**
	 * Fetches an embeddable video to a IMDB trailer from http://www.traileraddict.com
	 *
	 * @param $imdbID
	 *
	 * @return string
	 */
	public static function imdb_trailers($imdbID): string
	{
		$xml = Utility::getUrl(['url' => 'http://api.traileraddict.com/?imdb=' . $imdbID]);
		if ($xml !== false) {
			if (preg_match('#(v\.traileraddict\.com/\d+)#i', $xml, $html)) {
				return 'https://' . $html[1];
			}
		}
		return '';
	}

	/**
	 * Check if O/S is windows.
	 *
	 * @return bool
	 */
	public static function isWindows(): bool
	{
		return Utility::isWin();
	}

	/**
	 * Convert obj to array.
	 *
	 * @param       $arrObjData
	 * @param array $arrSkipIndices
	 *
	 * @return array
	 */
	public static function objectsIntoArray($arrObjData, array $arrSkipIndices = []): array
	{
		$arrData = [];

		// If input is object, convert into array.
		if (is_object($arrObjData)) {
			$arrObjData = get_object_vars($arrObjData);
		}

		if (is_array($arrObjData)) {
			foreach ($arrObjData as $index => $value) {
				// Recursive call.
				if (is_object($value) || is_array($value)) {
					$value = Utility::objectsIntoArray($value, $arrSkipIndices);
				}
				if (in_array($index, $arrSkipIndices, false)) {
					continue;
				}
				$arrData[$index] = $value;
			}
		}

		return $arrData;
	}

	/**
	 * Run CLI command.
	 *
	 * @param string $command
	 * @param bool   $debug
	 *
	 * @return array
	 */
	public static function runCmd($command, $debug = false)
	{
		$nl = PHP_EOL;
		if (Utility::isWindows() && strpos(PHP_VERSION, '5.3') !== false) {
			$command = "\"" . $command . "\"";
		}

		if ($debug) {
			echo '-Running Command: ' . $nl . '   ' . $command . $nl;
		}

		$output = [];
		$status = 1;
		@exec($command, $output, $status);

		if ($debug) {
			echo '-Command Output: ' . $nl . '   ' . implode($nl . '  ', $output) . $nl;
		}

		return $output;
	}

	/**
	 * Remove unsafe chars from a filename.
	 *
	 * @param string $filename
	 *
	 * @return string
	 */
	public static function safeFilename($filename)
	{
		return trim(preg_replace('/[^\w\s.-]*/i', '', $filename));
	}

	public static function generateUuid()
	{
		$key = sprintf
		(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			// 32 bits for "time_low"
			random_int(0, 0xffff), random_int(0, 0xffff),
			// 16 bits for "time_mid"
			random_int(0, 0xffff),
			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 4
			random_int(0, 0x0fff) | 0x4000,
			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			random_int(0, 0x3fff) | 0x8000,
			// 48 bits for "node"
			random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
		);

		return $key;
	}

	public static function startsWith($haystack, $needle)
	{
		return (strpos($haystack, $needle) === 0);
	}

	public static function endsWith($haystack, $needle)
	{
		$length = strlen($needle);
		$start = $length * -1;

		return (substr($haystack, $start) === $needle);
	}

	public static function responseXmlToObject($input)
	{
		$input = str_replace('<newznab:', '<', $input);

		return @simplexml_load_string($input);
	}

	/**
	 * @note: Convert non-UTF-8 characters into UTF-8
	 * Function taken from http://stackoverflow.com/a/19366999
	 *
	 * @param $data
	 *
	 * @return array|string
	 */
	public static function encodeAsUTF8($data)
	{
		if (is_array($data)) {
			foreach ($data as $key => $value) {
				$data[$key] = Utility::encodeAsUTF8($value);
			}
		} else {
			if (is_string($data)) {
				return utf8_encode($data);
			}
		}

		return $data;
	}

	/**
	 * This function turns a roman numeral into an integer
	 *
	 * @param string $string
	 *
	 * @return int $e
	 */
	public static function convertRomanToInt($string): int
	{
		switch (strtolower($string)) {
			case 'i': $e = 1;
				break;
			case 'ii': $e = 2;
				break;
			case 'iii': $e = 3;
				break;
			case 'iv': $e = 4;
				break;
			case 'v': $e = 5;
				break;
			case 'vi': $e = 6;
				break;
			case 'vii': $e = 7;
				break;
			case 'viii': $e = 8;
				break;
			case 'ix': $e = 9;
				break;
			case 'x': $e = 10;
				break;
			case 'xi': $e = 11;
				break;
			case 'xii': $e = 12;
				break;
			case 'xiii': $e = 13;
				break;
			case 'xiv': $e = 14;
				break;
			case 'xv': $e = 15;
				break;
			case 'xvi': $e = 16;
				break;
			case 'xvii': $e = 17;
				break;
			case 'xviii': $e = 18;
				break;
			case 'xix': $e = 19;
				break;
			case 'xx': $e = 20;
				break;
			default:
				$e = 0;
		}
		return $e;
	}


	/**
	 * Display error/error code.
	 * @param int    $errorCode
	 * @param string $errorText
	 */
	public static function showApiError($errorCode = 900, $errorText = ''): void
	{
		if ($errorText === '') {
			switch ($errorCode) {
				case 100:
					$errorText = 'Incorrect user credentials';
					break;
				case 101:
					$errorText = 'Account suspended';
					break;
				case 102:
					$errorText = 'Insufficient privileges/not authorized';
					break;
				case 103:
					$errorText = 'Registration denied';
					break;
				case 104:
					$errorText = 'Registrations are closed';
					break;
				case 105:
					$errorText = 'Invalid registration (Email Address Taken)';
					break;
				case 106:
					$errorText = 'Invalid registration (Email Address Bad Format)';
					break;
				case 107:
					$errorText = 'Registration Failed (Data error)';
					break;
				case 200:
					$errorText = 'Missing parameter';
					break;
				case 201:
					$errorText = 'Incorrect parameter';
					break;
				case 202:
					$errorText = 'No such function';
					break;
				case 203:
					$errorText = 'Function not available';
					break;
				case 300:
					$errorText = 'No such item';
					break;
				case 500:
					$errorText = 'Request limit reached';
					break;
				case 501:
					$errorText = 'Download limit reached';
					break;
				case 910:
					$errorText = 'API disabled';
					break;
				default:
					$errorText = 'Unknown error';
					break;
			}
		}

		$response =
			"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
			'<error code="' . $errorCode .  '" description="' . $errorText . "\"/>\n";
		header('Content-type: text/xml');
		header('Content-Length: ' . strlen($response) );
		header('X-NNTmux: API ERROR [' . $errorCode . '] ' . $errorText);

		exit($response);
	}

	/**
	 * Simple function to reduce duplication in html string formatting
	 *
	 * @param $string
	 *
	 * @return string
	 */
	public static function htmlfmt($string): string
	{
		return htmlspecialchars($string, ENT_QUOTES, 'utf-8');
	}

	/**
	 * Convert multi to single dimensional array
	 * Code taken from http://stackoverflow.com/a/12309103
	 *
	 * @param $array
	 *
	 * @param $separator
	 *
	 * @return string
	 */
	public static function convertMultiArray($array, $separator): string
	{
		return implode("$separator",array_map(function($a) {return implode(',',$a);},$array));
	}
}
