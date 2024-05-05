<?php

declare(strict_types=1);

namespace flight\commands;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;

class ControllerCommand extends AbstractBaseCommand
{
    /**
     * Construct
     *
     * @param array<string,mixed> $config JSON config from .runway-config.json
     */
    public function __construct(array $config)
    {
        parent::__construct('make:controller', 'Create a controller', $config);
        $this->argument('<controller>', 'The name of the controller to create (with or without the Controller suffix)');
    }

    /**
     * Executes the function
     *
     * @return void
     */
    public function execute(string $controller)
    {
        $io = $this->app()->io();
        if (isset($this->config['app_root']) === false) {
            $io->error('app_root not set in .runway-config.json', true);
            return;
        }

        if (!preg_match('/Controller$/', $controller)) {
            $controller .= 'Controller';
        }

        $controllerPath = getcwd() . DIRECTORY_SEPARATOR . $this->config['app_root'] . 'controllers' . DIRECTORY_SEPARATOR . $controller . '.php';
        if (file_exists($controllerPath) === true) {
            $io->error($controller . ' already exists.', true);
            return;
        }

        if (is_dir(dirname($controllerPath)) === false) {
            $io->info('Creating directory ' . dirname($controllerPath), true);
            mkdir(dirname($controllerPath), 0755, true);
        }

        $file = new PhpFile();
        $file->setStrictTypes();

        $namespace = new PhpNamespace('app\\controllers');
        $namespace->addUse('flight\\Engine');

        $class = new ClassType($controller);
        $class->addProperty('app')
            ->setVisibility('protected')
            ->setType('flight\\Engine')
            ->addComment('@var Engine');
        $method = $class->addMethod('__construct')
            ->addComment('Constructor')
            ->setVisibility('public')
            ->setBody('$this->app = $app;');
        $method->addParameter('app')
            ->setType('flight\\Engine');

        $namespace->add($class);
        $file->addNamespace($namespace);

        $this->persistClass($controller, $file);

        $io->ok('Controller successfully created at ' . $controllerPath, true);
    }

    /**
     * Saves the class name to a file
     *
     * @param string    $controllerName  Name of the Controller
     * @param PhpFile   $file            Class Object from Nette\PhpGenerator
     *
     * @return void
     */
    protected function persistClass(string $controllerName, PhpFile $file)
    {
        $printer = new \Nette\PhpGenerator\PsrPrinter();
        file_put_contents(getcwd() . DIRECTORY_SEPARATOR . $this->config['app_root'] . 'controllers' . DIRECTORY_SEPARATOR . $controllerName . '.php', $printer->printFile($file));
    }
}
