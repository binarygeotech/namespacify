#!/usr/local/php5/bin/php
<?php

/**
 * Namespacify Command Line Tool
 *
 * Adds namespaces to all PHP classes of a given directory.
 *
 * PHP Version 5.3.10
 *
 * @category  console
 * @package   namespacify
 * @author    Florian Eckerstorfer <florian@theroadtojoy.at>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @copyright 2012 2bePUBLISHED Internet Services Austria GmbH
 */

define('NAMESPACIFY_VERSION', '0.0.1-dev');
define('NAMESPACIFY_ROOT_DIR', __DIR__);

//
// AUTOLOAD
//
require_once NAMESPACIFY_ROOT_DIR.'/vendor/autoload.php';

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

use Pub\Namespacify\Application;
use Pub\Namespacify\Command\NamespacifyCommand;
use Pub\Namespacify\DependencyInjection\Compiler\CompilerPass;

$file = NAMESPACIFY_ROOT_DIR .'/cache/container.php';

if (file_exists($file)) {
    require_once $file;
    $container = new NamespacifyContainerCache();
} else {
    $container = new ContainerBuilder();

    $loader = new YamlFileLoader($container, new FileLocator(NAMESPACIFY_ROOT_DIR));
    $loader->load(NAMESPACIFY_ROOT_DIR.'/config/services.yml');
    $container->compile();

    $dumper = new PhpDumper($container);
    file_put_contents($file, $dumper->dump(array('class' => 'NamespacifyContainerCache')));
}

$console = new Application("Namespacify", NAMESPACIFY_VERSION);

// Add namespacify command.
$namespacifyCommand = new NamespacifyCommand();
$namespacifyCommand->setContainer($container);
$console->add($namespacifyCommand);

$console->run();
