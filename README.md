# Pragma Docs

## Installation

In composer.json add:

	require {"pragma-framework/docs": "dev-master"}

And in scripts blocks:

	"scripts": {
		"post-package-update": [
            "Pragma\\Docs\\Helpers\\Migrate::postUpdate"
        ],
        "post-package-install": [
            "Pragma\\Docs\\Helpers\\Migrate::postInstall"
        ],
        "pre-package-install": [
            "Pragma\\Docs\\Helpers\\Migrate::preUpdate"
        ],
        "pre-package-uninstall": [
            "Pragma\\Docs\\Helpers\\Migrate::preUninstall"
        ]
	}

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
