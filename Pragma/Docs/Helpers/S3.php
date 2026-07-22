<?php

namespace Pragma\Docs\Helpers;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class S3
{
    private $client;
    private $bucket;

    public function __construct()
    {
        if(!self::isConfigured()){
            return;
        }

        $this->bucket = S3_BUCKET;

        $this->client = new S3Client([
            'version'                 => 'latest',
            'region'                  => S3_REGION,
            'endpoint'                => S3_ENDPOINT,
            'use_path_style_endpoint' => defined('S3_USE_PATH_STYLE') ? filter_var(S3_USE_PATH_STYLE, FILTER_VALIDATE_BOOLEAN) : false,
            'credentials' => [
                'key'    => S3_ACCESS_KEY,
                'secret' => S3_SECRET_KEY,
            ],
        ]);
    }

    public static function isConfigured(){
        if(defined('S3_BUCKET') && S3_BUCKET && defined('S3_REGION') && S3_REGION && defined('S3_ENDPOINT') && S3_ENDPOINT && defined('S3_ACCESS_KEY') && S3_ACCESS_KEY && defined('S3_SECRET_KEY') && S3_SECRET_KEY){
            return true;
        }

        return false;
    }

    public function upload(string $key, string $filePathOrContent)
    {
        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
                'SourceFile'   => $filePathOrContent,
                'ContentType' => mime_content_type($filePathOrContent)
            ]);
        } catch (S3Exception $e) {
            throw new \RuntimeException('Erreur upload S3: ' . $e->getMessage());
        }
    }

    public function download($doc, $attachment)
    {
        if ($this->exists($doc->uid)) {
            $params = [
                'Bucket' => $this->bucket,
                'Key'    => $doc->uid,
            ];

            if (isset($_SERVER['HTTP_RANGE'])) {
                $params['Range'] = $_SERVER['HTTP_RANGE'];
            }

            $result = $this->client->getObject($params);

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

            $stream = $result['Body'];
            $mime_type = $result['ContentType'];
            $size = $result['ContentLength'] ?? null;
			
			if (empty($mime_type) || $mime_type === false) {
				$mime_type = ($UserBrowser == 'IE' || $UserBrowser == 'Opera') ?
					'application/octetstream' : 'application/octet-stream';
			}

			ini_set('memory_limit', '1024M');
            header('Content-Type: ' . $mime_type);
            header('Access-Control-Expose-Headers: Content-Disposition');

            if ($attachment) {
                header('Content-Disposition: attachment; filename="' . $doc->name . '"');
            } else {
                header('Content-Disposition: inline; filename="' . $doc->nmae . '"');
            }

            if (isset($result['ContentRange'])) {
                header('HTTP/1.1 206 Partial Content');
                header('Content-Range: ' . $result['ContentRange']);
            }

            if ($size !== null) {
                header('Content-Length: ' . $size);
            }

            header('Accept-Ranges: bytes');
            header('Cache-Control: private');

            while (!$stream->eof()) {
                echo $stream->read(8192);
                flush();
            }

			return true;
		}
        
		return false;
    }

    public function delete(string $key)
    {
        if($this->exists($key)){
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);
        }
    }

    public function exists(string $key)
    {
        if(empty($key)){
            return false;
        }

        return $this->client->doesObjectExist($this->bucket, $key);
    }

    public function getPresignedUrl(string $key, string $expiration = '+20 minutes')
    {
        if($this->exists($key)){
            $command = $this->client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);

            $request = $this->client->createPresignedRequest($command, $expiration);

            return (string) $request->getUri();
        }
        
        return null;
    }
}