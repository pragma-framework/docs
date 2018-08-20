<?php
use Phinx\Migration\AbstractMigration;

class AddPublicToDocuments extends AbstractMigration {
	public function change() {
		$table = $this->table('documents');
		$table->addColumn("is_public", "boolean", ['after' => 'extension'])
				->update();
	}
}
