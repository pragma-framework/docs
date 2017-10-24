<?php

use Phinx\Migration\AbstractMigration;
use Pragma\Docs\Models\Document;

class AddUidToDocuments extends AbstractMigration
{
		public function change()
		{
			$table = $this->table('documents');
			$table->addColumn("uid", "string")
					->update();

			$docs = Document::all();
			if(!empty($docs)){
				foreach($docs as $d){
					$path_elems = explode('/', $d->path);
					if(!empty($path_elems)){
						$file = array_pop($path_elems);
						$uid = substr($file, 0, strlen($file) - (strlen($d->extension) + 1) );
						$d->uid = $uid;
						$d->save();
					}
					else{
						continue;
					}
				}
			}
		}
}
