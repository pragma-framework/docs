<?php

use Phinx\Migration\AbstractMigration;

class AllowNullOnDocsUpdatedAtCols extends AbstractMigration
{
    public function up()
    {
        $this->table('documents')
            ->changeColumn('updated_at', 'datetime', ['null' => true])
            ->changeColumn('updated_by', 'string', ['null' => true])
            ->update();
        $this->table('folders')
            ->changeColumn('updated_at', 'datetime', ['null' => true])
            ->changeColumn('updated_by', 'string', ['null' => true])
            ->update();
    }
    public function down()
    {
        $this->table('documents')
            ->changeColumn('updated_at', 'datetime')
            ->changeColumn('updated_by', 'string')
            ->update();
        $this->table('folders')
            ->changeColumn('updated_at', 'datetime')
            ->changeColumn('updated_by', 'string')
            ->update();
    }
}
