<?php

use Phinx\Migration\AbstractMigration;

class CreateDocumentTable extends AbstractMigration
{
    public function change()
    {
        if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
          $strategy = ! defined('ORM_UID_STRATEGY') ? 'php' : ORM_UID_STRATEGY;
          $table = $this->table('documents', ['id' => false, 'primary_key' => 'id']);
          switch($strategy){
            case 'mysql':
            case 'laravel-uuid':
              $table->addColumn('id', 'char', ['limit' => 36]);
              break;
            default:
            case 'php':
              $table->addColumn('id', 'char', ['limit' => 23]);
              break;
          }
        }
        else{
          $table = $this->table('documents');
        }
        $table->addColumn("name", "string")
              ->addColumn("path", "string")
              ->addColumn("size", "decimal")
              ->addColumn("extension", "string")
              ->addColumn("created_at", "datetime")
              ->addColumn("created_by", "string")
              ->addColumn("updated_at", "datetime")
              ->addColumn("updated_by", "string")
              ->create();
    }
}
