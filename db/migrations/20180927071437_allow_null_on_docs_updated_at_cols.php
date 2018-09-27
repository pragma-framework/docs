<?php

use Phinx\Migration\AbstractMigration;

class AllowNullOnDocsUpdatedAtCols extends AbstractMigration {
    public function change() {
        $this->table('documents')
            ->changeColumn('updated_at', 'datetime', ['null' => true])
            ->changeColumn('updated_by', 'string', ['null' => true])
            ->update();
        $this->table('folders')
            ->changeColumn('updated_at', 'datetime', ['null' => true])
            ->changeColumn('updated_by', 'string', ['null' => true])
            ->update();
    }
}
