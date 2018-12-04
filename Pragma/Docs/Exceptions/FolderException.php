<?php
namespace Pragma\Docs\Exceptions;

class FolderException extends \Exception{
	const EMPTY_NAME_ERROR = 1;
	const DUPLICATE_NAME_ERROR = 2;

	const EMPTY_NAME_MSG = 'This folder\'s name cannot be empty';
	const DUPLICATE_NAME_MSG = 'This folder\'s name is already used in this folder';

	public function __toString(){
		return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
	}
}
