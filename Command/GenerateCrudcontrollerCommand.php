<?php

namespace Rana\RanaToolsBundle\Command;

use Rana\RanaToolsBundle\Util\CommandUtil;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCrudcontrollerCommand extends ContainerAwareCommand
{
    private $filePaths;

    protected function configure()
    {
        $this
            ->setName('rana:generate:crudcontroller')
            ->setDescription('Create a basic CRUD controller for an entity')
            ->addArgument('Bundle:Entity', InputArgument::REQUIRED, 'Bundle name and entity name')
            ->addOption('option', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //Get arguments and options
        $bundleAndEntity = $input->getArgument('Bundle:Entity');

        //Generate values from options
        list($bundleName, $entityName) = CommandUtil::getBundleAndEntityArray($bundleAndEntity, $output);

        $this->initializeFilePaths($bundleName, $entityName, $output);
        $this->createController($bundleName, $entityName, $output);
        $this->updateRoutingImport($bundleName, $entityName, $output);
        $this->updateRoute($bundleName, $entityName, $output);
    }

    private function initializeFilePaths($bundleName, $entityName, $output)
    {
        $this->filePaths = array(
            'entity' => 'src/'.$bundleName.'/Entity/'.$entityName.'.php',
            'form' => 'src/'.$bundleName.'/Form/'.$entityName.'Type.php',
            'controllerTemplate' => __dir__ . '/Templates/controllerTemplate.txt',
            'controller' => 'src/'.$bundleName.'/Controller/'.$entityName.'Controller.php',
            'routingImport' => 'app/config/routing.yml',
            'routing' => 'src/'.$bundleName.'/Resources/config/routing.yml',
            'routeImportTemplate' => __dir__ . '/Templates/routeImportTemplate.txt',
            'routeTemplate' => __dir__ . '/Templates/routeTemplate.txt',
        );

        //Verify if the entity file exists
        if (!file_exists($this->filePaths['entity'])) {
            $output->writeln('<error>'.$this->filePaths['entity'].' not exist. Create it using rana:generate:entity command</error>');
            exit();
        }

        //Verify if the form file exists
        if (!file_exists($this->filePaths['form'])) {
            $output->writeln('<error>'.$this->filePaths['form'].' not exist. Create it using rana:generate:form command</error>');
            exit();
        }

        //Verify that the controller file does not exist
        if (file_exists($this->filePaths['controller'])) {
            $output->writeln('<error>'.$this->filePaths['controller'].' already exist.</error>');
            exit();
        }
    }

    private function createController($bundleName, $entityName, $output)
    {
        //Read controller template
        $newController = CommandUtil::readAndCloseFile($this->filePaths['controllerTemplate']);

        //Create controller
        $newController = CommandUtil::strReplace($newController, array(
            '@@Bundle@@' => $bundleName,
            '@@Entity@@' => $entityName,
            '@@EntityLowerCase@@' => strtolower($entityName),
            '@@EntityUnderscore@@' => CommandUtil::toUnderscore($entityName),
            '@@EntityOnlyUCFirst@@' => ucfirst(strtolower($entityName)),
            '@@EntityLCFirst@@' => lcfirst($entityName),
            '@@SectionName@@' => substr(implode(' ', preg_split('/(?=[A-Z])/', str_replace('Bundle', '', $bundleName))), 1)
        ));
        
        //Write controller
        CommandUtil::writeAndCloseFile($this->filePaths['controller'], $newController);
        $output->writeln('<info>'.$this->filePaths['controller'].' was created or modified.</info>');
    }

    private function updateRoutingImport($bundleName, $entityName, $output)
    {
        $routingImportfile = fopen($this->filePaths['routingImport'], 'r+') or die('Unable to update routing file!');
        $routingImport = fread($routingImportfile, filesize($this->filePaths['routingImport']));
        
        if (strpos($routingImport, '@'.$bundleName.'/Resources/config/routing.yml')) {
            fclose($routingImportfile);
            $output->writeln('<comment>No changes were added to '.$this->filePaths['routingImport'].' because the resource is already defined.</comment>');
        } else {
            //Read routing template
            $newRouteImport = CommandUtil::readAndCloseFile($this->filePaths['routeImportTemplate']);

            //Create and add route to config file
            $newRouteImport = CommandUtil::strReplace($newRouteImport, array(
                '@@BundleName@@' => $bundleName,
                '@@BundleNameUnderscore@@' => CommandUtil::toUnderscore($bundleName)
            ));
            
            //Write file
            fwrite($routingImportfile, $newRouteImport);
            fclose($routingImportfile);
            $output->writeln('<info>'.$this->filePaths['routingImport'].' updated.<info>');
        }
    }

    private function updateRoute($bundleName, $entityName, $output)
    {
        $routingfile = fopen($this->filePaths['routing'], 'r+') or die('Unable to update routing file!');
        $routing = fread($routingfile, filesize($this->filePaths['routing']));
        
        if (strpos($routing, $bundleName.'\Controller\\'.$entityName.'Controller')) {
            fclose($routingfile);
            $output->writeln('<comment>No changes were added to '.$this->filePaths['routing'].' because the resource is already defined.</comment>');
        } else {
            //Read routing template
            $newRoute = CommandUtil::readAndCloseFile($this->filePaths['routeTemplate']);

            //Create and add route to config file
            $newRoute = CommandUtil::strReplace($newRoute, array(
                '@@EntityLCFirst@@' => lcfirst($entityName),
                '@@Bundle@@' => $bundleName,
                '@@Entity@@' => $entityName,
            ));

            fwrite($routingfile, $newRoute);
            fclose($routingfile);

            $output->writeln('<info>'.$this->filePaths['routing'].' updated.<info>');
        }
    }
}