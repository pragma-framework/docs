<?php
namespace Pragma\Docs\Exceptions;

class DocumentException extends \Exception{
	const CANT_MOVE_ERROR = 1;
	const CANT_BUILD_PATH_ERROR = 2;

	const CANT_MOVE_MSG = 'Can\'t move file: %s';
	const CANT_COPY_MSG = 'Can\'t copy file: %s';
	const CANT_BUILD_PATH_MSG = 'Can\'t build path: %s';

	public function __toString(){
		return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
	}
}
