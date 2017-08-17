<?php
namespace Pragma\Docs\Models;

use Pragma\ORM\Model;
use Pragma\Docs\Models\Document;
use Pragma\Docs\Exceptions\FolderException;

class Folder extends Model{
    CONST TABLENAME = 'folders';

    protected $children = array();
    protected $childrenInitialized = false;

    public function __construct(){
        $this->pushHook('after_open', 'initOldFields');
        $this->pushHook('before_save', 'testFolderName');
        $this->pushHook('before_save', 'detectChangeFolder');
        $this->pushHook('before_delete', 'deleteFolder');
        return parent::__construct(self::getTableName());
    }

    public static function getTableName(){
        defined('DB_PREFIX') OR define('DB_PREFIX','pragma_');
        return DB_PREFIX.self::TABLENAME;
    }

    public function save(){
        if($this->is_new()){
            $this->created_at = date('Y-m-d H:i:s');
            if(!empty($this->parent_id)){
                $this->updateRootId();
            }
        }else{
            $this->updated_at = date('Y-m-d H:i:s');
        }
        return parent::save();
    }

    public function getChildren(){
        if(empty($this->children) && !$this->childrenInitialized){
            $this->initChildren();
        }
        return $this->children;
    }

    public function initChildren(){
        if( ! $this->new && ! is_null($this->id) && !empty($this->id)){
            $this->childrenInitialized = true;
            $ref = empty($this->root_id) ? $this->id : $this->root_id; // Prbl qd on est sur un changement
            $children = self::forge()
                ->where('root_id', '=', $ref)
                ->order('name', 'DESC')
                ->get_objects();

            $family = array();
            foreach($children as $c){
                $family[$c->parent_id][] = $c;
            }
            if( !empty($family)){
                $temp = array();
                $temp[] = $this;
                while(!empty($temp)){
                    $current = array_shift($temp);
                    if(!empty($family[$current->id])){
                        foreach($family[$current->id] as $fam){
                            $current->children[$fam->id] = $fam;
                            $current->childrenInitialized = true;
                            array_unshift($temp, $fam);
                        }
                    }
                }
            }

            return $this;
        }
    }

    public function initOldFields($force = false){
        if(! $this->initialized || $force){
            $this->initial_values = $this->fields;
            $this->initialized = true;
        }
    }

    public function testFolderName(){
        // Empty name
        $this->name = trim($this->name);
        if(empty($this->name)){
            throw new FolderException(FolderException::EMPTY_NAME_MSG, FolderException::EMPTY_NAME_ERROR);
        }

        // Folder's name unicity
        $existing = self::forge()
            ->where('parent_id', '=', $this->parent_id)
            ->where('name', 'LIKE', $this->name);
         if(!$this->is_new() && ! is_null($this->id) && !empty($this->id)){
            $existing = $existing->where('id', '!=', $this->id);
        }
        $existing = $existing->limit(1)
            ->get_arrays();
        if(!empty($existing)){
            throw new FolderException(FolderException::DUPLICATE_NAME_MSG, FolderException::DUPLICATE_NAME_ERROR);
        }
    }

    public function detectChangeFolder(){
        if(!$this->is_new() && ! is_null($this->id) && !empty($this->id) && $this->initial_values['parent_id'] != $this->parent_id){
            $newFold = $this->parent_id;
            $this->parent_id = $this->initial_values['parent_id'];
            $this->initChildren();
            $this->parent_id = $newFold;

            if(!empty($this->parent_id)){
                $father = self::find($this->parent_id);
                $this->root_id = empty($father->root_id)? $father->id : $father->root_id;
            }else{
                $desc = $this->describe();
                $this->root_id = $desc['root_id']; // Set default value
            }

            $temp = $this->children;
            while( ! empty($temp) ){
                $sub = array_shift($temp);
                $sub->root_id = empty($this->root_id) ? $this->id : $this->root_id;
                $sub->save();
                if(!empty($sub->children)){
                    foreach($sub->children as $subsub){
                        array_unshift($temp, $subsub);
                    }
                }
            }
        }
    }

    public function deleteFolder(){
        if( ! $this->new && ! is_null($this->id) && !empty($this->id)){
            // Delete documents
            $files = Document::forge()
                ->where('folder_id', '=', $this->id)
                ->get_objects();
            foreach($files as $f){
                $f->delete();
            }

            // Delete folders
            $childrens = $this->getChildren();
            foreach($this->children as $c){
                $c->delete();
            }
        }
    }

    protected function updateRootId(){
        if( empty($this->parent_id) ){
            return false;
        }
        $parent = static::find($this->parent_id);
        if( empty($parent) ){
            return false;
        }

        $this->root_id = empty($parent->root_id) ? $parent->id : $parent->root_id;
    }
}
