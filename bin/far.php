<?php
use Symfony\Component\Console\Application;
use IjorTengab\FilesBulkOperation\Command\RepositionCommand;

$application = new Application();

$application->add(new RepositionCommand());

$application->run();
