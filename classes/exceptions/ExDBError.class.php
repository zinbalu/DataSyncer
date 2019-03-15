<?php
class ExDBError extends ExException {
	protected $sDefaultMessage = 'Error extracting data from DB.';
	protected $nDefaultCode = 4;
}
