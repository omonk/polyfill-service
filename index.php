<?php

/* Polyfill.io
   ========================================================================== */

$fileLastModified = gmdate('D, d M Y H:i:s T', filemtime(__FILE__));
$fileMD5 = md5_file(__FILE__);
$fileDir = dirname(__FILE__).'/';

$headLastModified = $_SERVER['HTTP_IF_MODIFIED_SINCE'];
$headMD5 = trim($_SERVER['HTTP_IF_NONE_MATCH']);

header('Cache-Control: public');
header('Content-Type: application/javascript');
header('Etag: '.$fileMD5);
header('Last-Modified: '.$fileLastModified, true, 200);

if ($fileLastModified == $headLastModified || $fileMD5 == $headMD5) {
	header($_SERVER['SERVER_PROTOCOL'].' 304 Not Modified');

	exit();
}

$is_source = isset($_GET['!']);
$agentList = json_decode(file_get_contents($fileDir.'agent.json'), true);
$polyfillList = json_decode(file_get_contents($fileDir.'polyfill.json'), true);

$thisAgentString = $_SERVER['HTTP_USER_AGENT'];

foreach ($agentList as $agentString => &$agentArray) {
	$agentBoolean = preg_match($agentArray['browser'], $thisAgentString, $agentMatches);

	if ($agentBoolean) {
		$buffer = array();

		foreach ($agentArray['version'] as $versionString) {
			$versionBoolean = preg_match($versionString, $thisAgentString, $versionMatches);

			if ($versionBoolean) {
				$versionString = $versionBoolean ? intval($versionMatches[1]) : 0;

				foreach ($polyfillList[$agentString] as $polyfillArray) {
					$min = isset($polyfillArray['only']) ? $polyfillArray['only'] : (isset($polyfillArray['min']) ? $polyfillArray['min'] : -INF);
					$max = isset($polyfillArray['only']) ? $polyfillArray['only'] : (isset($polyfillArray['max']) ? $polyfillArray['max'] : +INF);

					if ($versionString >= $min && $versionString <= $max) {
						$fillList = explode(' ', $polyfillArray['fill']);

						foreach ($fillList as $fillString) {
							$file = $is_source ? 'source/'.$fillString.'.js' : 'minified/'.$fillString.'.js';

							if (file_exists($file)) {
								array_push($buffer, file_get_contents($file));
							}
						}
					}
				}

				array_unshift($buffer, '// '.$agentString.' '.$versionString.' Polyfill'.($is_source ? '' : PHP_EOL));

				exit(implode($is_source ? PHP_EOL.PHP_EOL : '', $buffer));
			}
		}
	}
}