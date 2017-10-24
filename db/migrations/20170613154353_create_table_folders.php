<?php

use Phinx\Migration\AbstractMigration;

class CreateTableFolders extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $tableDoc = $this->table('documents');
        if(defined('ORM_ID_AS_UID') && ORM_ID_AS_UID){
          $strategy = ! defined('ORM_UID_STRATEGY') ? 'php' : ORM_UID_STRATEGY;
          $table = $this->table('folders', ['id' => false, 'primary_key' => 'id']);
          switch($strategy){
            case 'mysql':
            case 'laravel-uuid':
              $table->addColumn('id', 'char', ['limit' => 36])
                ->addColumn('parent_id', 'char', ['limit' => 36, 'default'=>null])
                ->addColumn('root_id', 'char', ['limit' => 36, 'default'=>null]);
              $tableDoc->addColumn('folder_id', 'char', ['limit'=>36, 'default'=>null, 'after'=>'id']);
              break;
            default:
            case 'php':
              $table->addColumn('id', 'char', ['limit' => 23])
                ->addColumn('parent_id', 'char', ['limit' => 23, 'default'=>null])
                ->addColumn('root_id', 'char', ['limit' => 23, 'default'=>null]);
              $tableDoc->addColumn('folder_id', 'char', ['limit'=>23, 'default'=>null, 'after'=>'id']);
              break;
          }
        }
        else{
          $table = $this->table('folders');
          $table->addColumn('parent_id', 'integer', ['default' => 0])
            ->addColumn('root_id', 'integer', ['default' => 0]);
          $tableDoc->addColumn('folder_id', 'integer', ['default'=>0, 'after'=>'id']);
        }
        $table->addColumn("name", "string")
          ->addColumn("created_at", "datetime")
          ->addColumn("created_by", "string")
          ->addColumn("updated_at", "datetime")
          ->addColumn("updated_by", "string")
          ->addIndex("parent_id")
          ->addIndex("root_id")
          ->create();
        $tableDoc->addIndex('folder_id')
          ->update();
    }
}
