<?php

use Phinx\Migration\AbstractMigration;

use Pragma\Docs\Document;

class CreateDocumentTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table(Document::getTableName());
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
