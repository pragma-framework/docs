<?php

namespace Pragma\Docs\Helpers;

class FileDownload
{
	public static function download($path, $file_name, $extension = '', $attachment = true)
	{
		if (file_exists($path)) {
			ob_clean();
			error_reporting(0);

			$UserBrowser = '';
			if (!empty($_SERVER['HTTP_USER_AGENT'])) {
				if (preg_match('#Opera(/| )([0-9].[0-9]{1,2})#', $_SERVER['HTTP_USER_AGENT']) !== false) {
					$UserBrowser = "Opera";
				} elseif (preg_match('#MSIE ([0-9].[0-9]{1,2})#', $_SERVER['HTTP_USER_AGENT']) !== false) {
					$UserBrowser = "IE";
				}
			}

			if (function_exists('mime_content_type')) {
				$mime_type = mime_content_type($path);
			} elseif (function_exists('finfo_open')) {
				$finfo = finfo_open(FILEINFO_MIME);
				$mime_type = finfo_file($finfo, $path);
				finfo_close($finfo);
			} else {
				// important for download im most browser
				$mime_type = ($UserBrowser == 'IE' || $UserBrowser == 'Opera') ?
					'application/octetstream' : 'application/octet-stream';

				$repository = new \Dflydev\ApacheMimeTypes\PhpRepository();
				$mime_type = $repository->findType($extension);
			}
			if (empty($mime_type) || $mime_type === false) {
				$mime_type = ($UserBrowser == 'IE' || $UserBrowser == 'Opera') ?
					'application/octetstream' : 'application/octet-stream';
			}

			ini_set('memory_limit', '1024M');
			header('Content-Type: ' . $mime_type);
			header('Access-Control-Expose-Headers: Content-Disposition'); // permet d'avoir accès au filename depuis axios
			if ($attachment) {
				@ini_set('zlib.output_compression', 'Off');

				// new download function works with IE6+SSL(http://fr.php.net/manual/fr/function.header.php#65404)
				$path = rawurldecode($path);
				$size = filesize($path);

				header('Content-Disposition: attachment; filename="' . $file_name . '"');
				header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
				header('Accept-Ranges: bytes');
				header('Cache-control: private');
				header('Pragma: private');

				@ob_end_clean();
				//while (ob_get_contents()) @ob_end_clean();
				//@set_time_limit(3600);

				ob_end_flush();

				/////  multipart-download and resume-download
				if (isset($_SERVER['HTTP_RANGE'])) {
					list($a, $range) = explode("=", $_SERVER['HTTP_RANGE']);
					str_replace($range, "-", $range);
					$size2 = $size - 1;
					$new_length = $size - $range;
					header("HTTP/1.1 206 Partial Content");
					header("Content-Length: $new_length");
					header("Content-Range: bytes $range$size2/$size");
				} else {
					$size2 = $size - 1;
					header("Content-Length: " . $size);
				}

				@ob_flush();
				@flush();
				@readfile($path);

				if (isset($new_length)) {
					$size = $new_length;
				}
			} else {
				header("Content-disposition: inline; filename={$file_name}");
				header("Content-Length: " . filesize($path));
				header("Pragma: no-cache");
				header("Cache-Control: must-revalidate, post-check=0, pre-check=0, public");
				header("Expires: 0");

				@readfile($path);
			}
			return true;
		}
		return false;
	}
}
