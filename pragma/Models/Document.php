<?php
namespace Pragma\Models;

use Pragma\ORM\Model;

class Document extends Model{
	CONST TABLENAME = 'documents';

	protected $upload_path = 'uploads';

	public function __construct(){
		// base on ./vendor/pragma-framework/docs/Pragma/Models/ path
		defined('DOC_STORE') OR define('DOC_STORE',realpath(__DIR__.'/../../../../').'/data/');
        return parent::__construct(self::getTableName());
    }

    public static function getTableName(){
		defined('DB_PREFIX') OR define('DB_PREFIX','pragma_');
    	return DB_PREFIX.self::TABLENAME;
    }

    public function save(){
		if($this->is_new()){
			$this->created_at = date('Y-m-d H:i:s');
			$this->created_by = self::buildUserLabel();
		}else{
			$this->updated_at = date('Y-m-d H:i:s');
			$this->updated_by = self::buildUserLabel();
		}

		return parent::save();
	}

	protected static function buildUserLabel(){
		if(isset($_SESSION['user']['fullname'])){
			return $_SESSION['user']['fullname'];
		}elseif(isset($_SESSION['user']['firstname']) && isset($_SESSION['user']['lastname'])){
			return $_SESSION['user']['firstname']." ".$_SESSION['user']['lastname'];
		}elseif(isset($_SESSION['user']['email'])){
			return $_SESSION['user']['email'];
		}elseif(isset($_SESSION['user']['login'])){
			return $_SESSION['user']['login'];
		}else{
			return "";
		}
	}

	public function handle_file($file){
		try{
			if( ! $this->is_new() ){//on doit supprimer physiquement l'ancien fichier
				$this->delete_physical_file();
			}
			//on doit déplacer le fichier
			$tmp_name = $file["tmp_name"];
			$extension = strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
			$context = date('Y/m');
			$finalfilename = uniqid() . '.' . $extension;
			$path = $context . '/' . $finalfilename;
			$realpath = $this->build_path($context).'/'.$finalfilename;
			move_uploaded_file($tmp_name, $realpath);
			$this->name = $file["name"];
			$this->size = $file["size"];
			$this->path = $path;
			$this->extension = $extension;
			return true;
		}
		catch(\Exception $e){
			return false;
		}
	}

	protected function build_path($context){
		$path = DOC_STORE.$this->upload_path.(substr($context,0,1) == '/'?'':'/').$context;
		if( ! file_exists($path) ){
			$oldumask = umask(0);
			mkdir($path, 0775, true); // or even 01777 so you get the sticky bit set
			umask($oldumask);
		}
		return $path;
	}

	public function get_full_path(){
		return DOC_STORE.$this->upload_path.'/'.$this->path;
	}

	protected function delete_physical_file(){
		if(file_exists($this->get_full_path())){
			unlink($this->get_full_path());
		}
	}

	public function downloadfile($attachment = true) {
		ob_clean();
		error_reporting(0);

		$filepath = $this->path;

		if (file_exists($this->path)) {
			$UserBrowser = '';
			if (!empty($_SERVER['HTTP_USER_AGENT'])) {
				if (preg_match('Opera(/| )([0-9].[0-9]{1,2})', $_SERVER['HTTP_USER_AGENT']) !== false) {
					$UserBrowser = "Opera";
				} elseif (preg_match('MSIE ([0-9].[0-9]{1,2})', $_SERVER['HTTP_USER_AGENT']) !== false) {
					$UserBrowser = "IE";
				}
			}

			if(function_exists('mime_content_type')){
				$mime_type = mime_content_type($this->path);
			}elseif(function_exists('finfo_open')){
				$finfo = finfo_open(FILEINFO_MIME);
				$mime_type = finfo_file($finfo, $this->path);
				finfo_close($finfo);
			}else{
				/// important for download im most browser
				$mime_type = ($UserBrowser == 'IE' || $UserBrowser == 'Opera') ?
				 'application/octetstream' : 'application/octet-stream';
				switch(strrchr(basename($this->name), '.')) {
					case ".gz": $mime_type = "application/x-gzip"; break;
					case ".tgz": $mime_type = "application/x-gzip"; break;
					case ".zip": $mime_type = "application/zip"; break;
					case ".pdf": $mime_type = "application/pdf"; break;
					case ".doc": $mime_type = "application/msword"; break;
					case ".ppt": $mime_type = "application/mspowerpoint"; break;
					case ".xls": $mime_type = "application/excel"; break;
					case ".png": $mime_type = "image/png"; break;
					case ".gif": $mime_type = "image/gif"; break;
					case ".jpeg": $mime_type = "image/jpeg"; break;
					case ".jpg": $mime_type = "image/jpeg"; break;
					case ".txt": $mime_type = "text/plain"; break;
					case ".htm": $mime_type = "text/html"; break;
					case ".html": $mime_type = "text/html"; break;
				}
			}
			if(empty($mime_type) || $mime_type === false){
				$mime_type = ($UserBrowser == 'IE' || $UserBrowser == 'Opera') ?
				 'application/octetstream' : 'application/octet-stream';
			}

			ini_set('memory_limit','512M');
			if ($attachment) {
				@ini_set('zlib.output_compression', 'Off');

				// new download function works with IE6+SSL(http://fr.php.net/manual/fr/function.header.php#65404)
				$filepath = rawurldecode($this->path);
				$size = filesize($filepath);

				header('Content-Type: ' . $mime_type);
				header('Content-Disposition: attachment; filename="'.$this->name.'"');
				header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
				header('Accept-Ranges: bytes');
				header('Cache-control: private');
				header('Pragma: private');

				@ob_end_clean();
				//while (ob_get_contents()) @ob_end_clean();
				//@set_time_limit(3600);

				ob_end_flush();

				/////  multipart-download and resume-download
				if(isset($_SERVER['HTTP_RANGE'])) {

					list($a, $range) = explode("=",$_SERVER['HTTP_RANGE']);
					str_replace($range, "-", $range);
					$size2 = $size-1;
					$new_length = $size-$range;
					header("HTTP/1.1 206 Partial Content");
					header("Content-Length: $new_length");
					header("Content-Range: bytes $range$size2/$size");
				} else {
					$size2=$size-1;
					header("Content-Length: ".$size);
				}

				@readfile($filepath);
				@ob_flush();
				@flush();
				
				if (isset($new_length)) {
					$size = $new_length;
				}
			} else {
				header("Content-disposition: inline; filename={$this->name}");
				header('Content-Type: ' . $mime_type);
				header("Content-Length: ".filesize($filepath));
				header("Pragma: no-cache");
				header("Cache-Control: must-revalidate, post-check=0, pre-check=0, public");
				header("Expires: 0");

				@readfile($filepath);
			}
			die();

		} else {
			return(false);
		}
	}
}
