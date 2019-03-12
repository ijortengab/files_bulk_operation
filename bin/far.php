<?php
use Symfony\Component\Console\Application;
use IjorTengab\FilesAutoReposition\Command\RepositionCommand;


$application = new Application();

$debugname = 'application'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";

$application->add(new RepositionCommand());

$application->run();