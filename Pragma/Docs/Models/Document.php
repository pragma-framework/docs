<?php
namespace Pragma\Docs\Models;

use Pragma\ORM\Model;

class Document extends Model{
    CONST TABLENAME = 'documents';

    protected $upload_path = 'uploads';
    protected $has_physical_file_changed = false;
    protected $validExtensions = [];

    public function __construct(){
        // base on ./vendor/pragma-framework/docs/Pragma/Docs/Models/ path
        defined('DOC_STORE') OR define('DOC_STORE',realpath(__DIR__.'/../../../../../../').'/data/');
        return parent::__construct(self::getTableName());
    }

    public static function getTableName(){
        defined('DB_PREFIX') OR define('DB_PREFIX','pragma_');
        return DB_PREFIX.self::TABLENAME;
    }

    public function save(){
        if($this->is_new()){
            $this->created_at = date('Y-m-d H:i:s');
        }else{
            $this->updated_at = date('Y-m-d H:i:s');
        }

        if($this->checkIsValidExtensions()){
            parent::save();
            if($this->is_new()){
                $this->delete_physical_file();
            }
            return $this;
        }else{
            $this->delete_physical_file();
            return $this;
        }
    }

    public function delete(){
        if( ! $this->new && ! is_null($this->id) && !empty($this->id)){
            $this->delete_physical_file();
            parent::delete();
        }
    }

    public function cloneDoc(){
        $filepath = $this->get_full_path();
        $path = "";
        if (file_exists($filepath) && !empty($this->path)) {
            $context = date('Y/m');
            $finalfilename = uniqid() . '.' . $this->extension;
            $path = $context . '/' . $finalfilename;
            $realpath = $this->build_path($context).'/'.$finalfilename;
            copy($filepath, $realpath);
        }
        return self::build(array(
            'name' => $this->name,
            'size' => $this->size,
            'extension' => $this->extension,
            'path' => $path,
        ))->save();
    }

    public function handle_file($file){
        try{
            $this->has_physical_file_changed = true;
            if( ! $this->is_new() ){//on doit supprimer physiquement l'ancien fichier
                $this->delete_physical_file();
            }
            //on doit déplacer le fichier
            $tmp_name = $file["tmp_name"];
            $extension = strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
            $context = date('Y/m');
            $this->uid = uniqid();
            $finalfilename = $this->uid . '.' . $extension;
            $path = $context . '/' . $finalfilename;
            $realpath = $this->build_path($context).'/'.$finalfilename;

            if (!move_uploaded_file($tmp_name, $realpath)) {
                throw new \Exception('Can\'t move file: '.(string)$tmp_name);
            }

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

    public function has_physical_file_changed(){
        return $this->has_physical_file_changed;
    }

    protected function build_path($context){
        $path = DOC_STORE.$this->upload_path.(substr($context,0,1) == '/'?'':'/').$context;
        if( ! file_exists($path) ){
            $oldumask = umask(0);

            // or even 01777 so you get the sticky bit set
            if (!mkdir($path, 0775, true)) {
                throw new \Exception('Can\'t build path: '.(string)$path);
            }

            umask($oldumask);
        }
        return $path;
    }

    public function get_full_path(){
        return DOC_STORE.$this->upload_path.'/'.$this->path;
    }

    protected function delete_physical_file(){
        if(file_exists($this->get_full_path()) && !empty($this->path)){
            unlink($this->get_full_path());
        }
    }

    public function downloadfile($attachment = true) {
        ob_clean();
        error_reporting(0);

        $filepath = $this->get_full_path();

        if (file_exists($filepath) && !empty($this->path)) {
            $UserBrowser = '';
            if (!empty($_SERVER['HTTP_USER_AGENT'])) {
                if (preg_match('#Opera(/| )([0-9].[0-9]{1,2})#', $_SERVER['HTTP_USER_AGENT']) !== false) {
                    $UserBrowser = "Opera";
                } elseif (preg_match('#MSIE ([0-9].[0-9]{1,2})#', $_SERVER['HTTP_USER_AGENT']) !== false) {
                    $UserBrowser = "IE";
                }
            }

            if(function_exists('mime_content_type')){
                $mime_type = mime_content_type($filepath);
            }elseif(function_exists('finfo_open')){
                $finfo = finfo_open(FILEINFO_MIME);
                $mime_type = finfo_file($finfo, $filepath);
                finfo_close($finfo);
            }else{
                /// important for download im most browser
                $mime_type = ($UserBrowser == 'IE' || $UserBrowser == 'Opera') ?
                 'application/octetstream' : 'application/octet-stream';

                $repository = new \Dflydev\ApacheMimeTypes\PhpRepository();
                $mime_type = $repository->findType(substr(strrchr(basename($this->name), '.'), 1));
            }
            if(empty($mime_type) || $mime_type === false){
                $mime_type = ($UserBrowser == 'IE' || $UserBrowser == 'Opera') ?
                 'application/octetstream' : 'application/octet-stream';
            }

            ini_set('memory_limit','512M');
            if ($attachment) {
                @ini_set('zlib.output_compression', 'Off');

                // new download function works with IE6+SSL(http://fr.php.net/manual/fr/function.header.php#65404)
                $filepath = rawurldecode($filepath);
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

    /*
    * Return the text content of the document
    * Based on the tool textract available at https://github.com/dbashford/textract
    * Requirements :
    * All OS :
    * - pdftotext
    * - tesseract
    * - drawingtotext (for DXF files)
    * Not for OSX :
    * - antiword
    * - unrtf
    */
    protected function extract_text($preserveLinesBreaks = true) {
        ini_set('max_execution_time',0);
        $content = '';
        if (file_exists($this->get_full_path())) {
            $pathexec = str_replace(" ","\ ",$this->get_full_path());
            $a = $b = null;
            $extrapath = defined("EXTRA_PATH") ? 'PATH=$PATH:'.EXTRA_PATH : '';
            $content = shell_exec(escapeshellcmd($extrapath . ' textract '.escapeshellarg($pathexec).' --preserveLineBreaks '. ($preserveLinesBreaks ? 'true' : 'false')));
        }
        return $content;
    }

    /*
    Permet de définir une liste d'extensions attendues
    Ex: ['pdf', 'doc', 'docx']
     */
    public function defineValidExtentions($extensions = []){
        $this->validExtensions = $extensions;
        return $this;
    }

    public function checkIsValidExtensions(){
        $path = $this->get_full_path();
        if(!empty($path) && file_exists($path) && !empty($this->validExtensions)){
            if(function_exists('mime_content_type')){
                $mime_type = mime_content_type($path);
            }elseif(function_exists('finfo_open')){
                $finfo = finfo_open(FILEINFO_MIME);
                $mime_type = finfo_file($finfo, $path);
                finfo_close($finfo);
            }else{
                return in_array($this->extension, $this->validExtensions);
            }

            if(!empty($mime_type)){
                $repository = new \Dflydev\ApacheMimeTypes\PhpRepository();
                $extensions = $repository->findExtensions($mime_type);

                foreach($extensions as $ext){
                    if(in_array($ext, $this->validExtensions)){
                        return true;
                    }
                }
            }
            return false; // Mime Type non trouvé
        }
        return true;
    }
}
