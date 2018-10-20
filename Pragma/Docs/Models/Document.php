<?php
namespace Pragma\Docs\Models;

use Pragma\ORM\Model;
use Pragma\Docs\Exceptions\DocumentException;

class Document extends Model{
    CONST TABLENAME = 'documents';

    protected $upload_path = 'uploads';
    protected $has_physical_file_changed = false;
    protected $validExtensions = [];

    protected $initial_public_status = null;
    protected $initial_fullpath = null;

    public function __construct(){
        // base on ./vendor/pragma-framework/docs/Pragma/Docs/Models/ path
        defined('DOC_STORE') || define('DOC_STORE',realpath(__DIR__.'/../../../../../../').'/data/');
        $this->pushHook('after_open', 'initPublicState');
        $this->pushHook('before_save', 'checkPublicChanges');
        $this->pushHook('after_save', 'initPublicState');
        return parent::__construct(self::getTableName());
    }

    public static function getTableName(){
        defined('DB_PREFIX') || define('DB_PREFIX','pragma_');
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

    protected function copy_physical_path($fullpath = null) {
        $filepath = is_null($fullpath) ? $this->get_full_path() : $fullpath;
        $path = "";
        $uid = "";
        if (file_exists($filepath) && !empty($this->path)) {
            $context = date('Y/m');
            $uid = $this->is_public ? uniqid('', true) : uniqid();
            $finalfilename = $uid . '.' . $this->extension;
            $path = $context . '/' . $finalfilename;
            $realpath = $this->build_path($context).'/'.$finalfilename;
            copy($filepath, $realpath);
        }
        return [$path, $uid];
    }

    public function cloneDoc() {
        list($path, $uid) = $this->copy_physical_path();
        return self::build(array(
            'name' => $this->name,
            'size' => $this->size,
            'extension' => $this->extension,
            'is_public' => $this->is_public,
            'path' => $path,
            'uid' => $uid,
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
            $extension = self::getExtension($file['tmp_name']);
            if(empty($extension)){
                $extension = strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
            }
            $context = date('Y/m');
            $this->uid = $this->is_public ? uniqid('', true) : uniqid();
            if (!empty($extension)) {
                $finalfilename = $this->uid . '.' . $extension;
            }
            else {
                $finalfilename = $this->uid;
            }
            $path = $context . '/' . $finalfilename;
            $realpath = $this->build_path($context).'/'.$finalfilename;

            if (is_uploaded_file($tmp_name)) {
                if (!move_uploaded_file($tmp_name, $realpath)) {
                    throw new DocumentException(sprintf(DocumentException::CANT_MOVE_MSG, (string)$tmp_name));
                }
            }
            else {
                if (!rename($tmp_name, $realpath)) {
                    throw new DocumentException(sprintf(DocumentException::CANT_MOVE_MSG, (string)$tmp_name));
                }
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
        $path = DOC_STORE.$this->upload_path.($this->is_public ? '/public/' : '').(substr($context,0,1) == '/'?'':'/').$context;
        if( ! file_exists($path) ){
            $oldumask = umask(0);

            // or even 01777 so you get the sticky bit set
            if (!mkdir($path, 0775, true)) {
                throw new DocumentException(sprintf(DocumentException::CANT_BUILD_PATH_MSG, (string)$path));
            }

            umask($oldumask);
        }
        return $path;
    }

    public function get_full_path(){
        return DOC_STORE.$this->upload_path.($this->is_public ? '/public' : '').'/'.$this->path;
    }

    protected function delete_physical_file($fullpath = null){
        $filepath = is_null($fullpath) ? $this->get_full_path() : $fullpath;
        if(file_exists($filepath) && !empty($this->path)){
            unlink($filepath);
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
                $mime_type = $repository->findType($this->extension);
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
    Allow the developper to define a whitelist of extensions
    Ex: ['pdf', 'doc', 'docx']
     */
    public function defineValidExtentions($extensions = []){
        $this->validExtensions = $extensions;
        return $this;
    }

    protected static function getExtension($path){
        if(!empty($path) && file_exists($path)){
            if(function_exists('mime_content_type')){
                $mime_type = mime_content_type($path);
            }elseif(function_exists('finfo_open')){
                $finfo = finfo_open(FILEINFO_MIME);
                $mime_type = finfo_file($finfo, $path);
                finfo_close($finfo);
            }
            $extension = strtolower(pathinfo($path,PATHINFO_EXTENSION));
            if(!empty($mime_type)){
                $repository = new \Dflydev\ApacheMimeTypes\PhpRepository();
                $extensions = $repository->findExtensions($mime_type);

                if(empty($extension) || !in_array($extension, $extensions)){
                    return current($extensions);
                } // Else extension exist & return it
            }
            return $extension;
        }
        return '';
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

    public function setPublic() {
        $this->is_public = true;
    }

    protected function initPublicState() {
        $this->initial_public_status = $this->is_public;
        $this->initial_fullpath = $this->get_full_path();
    }

    protected function checkPublicChanges() {
        if(!is_null($this->initial_public_status) && $this->initial_public_status != $this->is_public) { //on doit déplacer le fichier
            list($path, $uid) = $this->copy_physical_path($this->initial_fullpath);
            $this->delete_physical_file($this->initial_fullpath);
            $this->path = $path;
            $this->uid = $uid;
        }
    }
}
