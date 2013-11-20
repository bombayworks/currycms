# Curry CMS

Curry CMS is an open-source Content Management System (CMS) for PHP 5.3.

## Requirements

* PHP 5.3.7 or later, with the DOM/libxml2 and PDO extension.
* A supported database (**MySQL**, MS SQL Server, PostgreSQL, SQLite, Oracle)
* Web server with support for URL rewriting

Curry CMS also depend on the following 3rd party libraries:

* Zend Framework
* Propel
* Twig
* Minify

## Getting started

To setup Curry CMS, you need a project with some minimal configuration. A project skeleton
can be found in the [currycms-project-base](https://github.com/bombayworks/currycms-project-base)
repository. You can use composer to create a new project using this repository.

* [Install composer](http://getcomposer.org)
* Create project skeleton and install dependencies `php composer.phar create-project --stability=dev bombayworks/currycms-project-base <directory>`
* Create curry symlink `ln -s vendor/bombayworks/currycms curry`

Once everything has been installed, you need to make the `www` folder accessible from your
web server and configure URL rewriting, after that you should be able to access the project
installation from `/admin.php`.

## Unit testing

Curry CMS uses PHPUnit for unit testing. In order to run the tests, you need to:

* Download composer development dependencies `php composer.phar install --dev`.
* Configure database settings in `test/fixtures/propel.xml`.
* Build propel files for fixtures `./vendor/bin/propel-gen test/fixtures/propel/ main`.
* Clear database `./vendor/bin/propel-gen test/fixtures/propel/ insert-sql`.
* Run tests: `./vendor/bin/phpunit`.

## License

See the `LICENSE.txt` file.
