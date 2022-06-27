<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Moahmed-Ismail MEJRI <imejri@hotmail.com>
 * @copyright Moahmed-Ismail MEJRI 2022
 */

namespace OCA\Music\Utility;

/**
 * MetaData radio utility functions
 */
class RadioMetadata {

	private static function findStr($data, $str) {
		$ret = "";
		foreach ($data as $value) {
			$find = strstr($value, $str);
			if ($find !== false) {
				$ret = $find;
				break;
			}
		}
		return $ret;
	}

	private static function parseStreamUrl($url) {

		$ret = array();
		$parse_url = parse_url($url);

		$ret['port'] = 80;
		if (isset($parse_url['port'])) {
			$ret['port'] = $parse_url['port'];
		} else if ($parse_url['scheme'] == "https") {
			$ret['port'] = 443;
		}

		$ret['hostname'] = $parse_url['host'];
		$ret['pathname'] = $parse_url['path'];

		if (isset($parse_url['query'])) {
			$ret['pathname'] .= "?" . $parse_url['query'];
		}

		if ($parse_url['scheme'] == "https") {
			$ret['sockadd'] = "ssl://" . $ret['hostname'];
		} else {
			$ret['sockadd'] = $ret['hostname'];
		}

		return $ret;
	}


	public static function fetchUrlData($url) : array {
		$content = \file_get_contents($url);
		list($version, $status_code, $msg) = explode(' ', $http_response_header[0], 3);
		return [$content, $status_code, $msg];
	}

	public static function fetchStreamData($url, $maxattempts, $maxredirect) {
		$timeout = 10;
		$streamTitle = "";
		$pUrl = self::parseStreamUrl($url);
		if (($pUrl['sockadd']) && ($pUrl['port'])) {
			$fp = fsockopen($pUrl['sockadd'], $pUrl['port'], $errno, $errstr, $timeout);
			if ($fp != false) {
				$out = "GET " . $pUrl['pathname'] . " HTTP/1.1\r\n";
				$out .= "Host: ". $pUrl['hostname'] . "\r\n";
				$out .= "Accept: */*\r\n"; /* test */
				$out .= "User-Agent: OCMusic/1.52\r\n";
				$out .= "Icy-MetaData: 1\r\n";
				$out .= "Connection: Close\r\n\r\n";
				fwrite($fp, $out);
				stream_set_timeout($fp, $timeout);

				$header = fread($fp, 1024);
				$headers = explode("\n", $header);

				if (strstr($headers[0], "200 OK") !== false) {
					$interval = 0;
					$line = self::findStr($headers, "icy-metaint:");
					if ($line) {
						$interval = trim(explode(':', $line)[1]);
					}

					if (($interval)&&($interval<64001)) {
						$attempts = 0;
						while ($attempts < $maxattempts) {
							for ($j = 0; $j < $interval; $j++) {
								fread($fp, 1);
							}

							$meta_length = ord(fread($fp, 1)) * 16;
							if ($meta_length) {
								$metadatas = explode(';', fread($fp, $meta_length));
								$metadata = self::findStr($metadatas, "StreamTitle");
								if ($metadata) {
									$streamTitle = trim(explode('=', $metadata)[1], "'");
									if (strlen($streamTitle) > 256) {
										$streamTitle = "";
									}
									break;
								}
							}
							$attempts++;
						}
					}
				} else if (($maxredirect>0)&&(strstr($headers[0], "302 Found") !== false)) {
					$value = self::findStr($headers, "Location:");
					if ($value) {
						$location = trim(substr($value, 10), "\r");
						$streamTitle = RadioMetadata::fetchStreamData($location, $maxattempts, $maxredirect-1);
					}
				}
				fclose($fp);
			}
		}
		return $streamTitle;
	}

}
