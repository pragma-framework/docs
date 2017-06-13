# Pragma Docs

## Installation

In composer.json add:

	require {"pragma-framework/docs": "dev-master"}

## Configuration

### Config.php

Options needed:

* DB_HOST (default 'localhost')
* DB_NAME
* DB_USER (default 'root')
* DB_PASSWORD
* DB_PREFIX (default 'pragma_')
* DOC_STORE (default 'data/')

`DOC_STORE` directory need read/write access for www-data.

## TODO

* maybe add directory model
