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
* EXTRA_PATH (default empty)

`DOC_STORE` directory need read/write access for www-data.

`PRAGMA_SET_CREATED_UPDATED_BY` is a generic method to define created_by & updated_by fields on documents and folders.

`EXTRA_PATH` is used in the Document::extract_text method. This method allows you to get the text content of a document and requires `textract` tool on the server. `EXTRA_PATH` is helpfull when the PATH accessible by PHP does not contain the directory where `textract` is.

As you already know, this functionnality requires the `textract` tool on the server (https://github.com/dbashford/textract) and several dependencies based on your OS :

#### All OS :

* pdftotext
* tesseract
* drawingtotext (for DXF files)

#### Not for OSX (textract will use textutil instead) :

* antiword
* unrtf
